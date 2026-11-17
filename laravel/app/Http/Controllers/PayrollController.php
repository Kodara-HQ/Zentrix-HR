<?php

namespace App\Http\Controllers;

use App\Enums\PayrollStatus;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Loan;
use App\Models\PayAdjustment;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Services\PayrollService;
use App\Support\Access;
use App\Support\Audit;
use App\Support\Format;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Payroll for officers and "My payslips" for everyone. Ported from server/src/routes/payroll.ts and the React Payroll page. */
class PayrollController extends Controller
{
    public const OFFICERS = ['HR_ADMIN', 'HR_MANAGER', 'PAYROLL_OFFICER'];

    public const APPROVERS = ['HR_ADMIN', 'HR_MANAGER'];

    public function __construct(private PayrollService $payroll) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $officer = Access::hasRole($user, self::OFFICERS);
        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);

        return view('payroll.index', [
            'officer' => $officer,
            'approver' => Access::hasRole($user, self::APPROVERS),
            'runs' => $officer ? PayrollRun::orderByDesc('year')->orderByDesc('month')->get() : collect(),
            'mine' => $user->employee_id ? Payslip::with('payrollRun')->where('employee_id', $user->employee_id)
                ->whereHas('payrollRun', fn ($q) => $q->where('status', PayrollStatus::APPROVED))
                ->get()->sortByDesc(fn ($s) => $s->payrollRun->year * 100 + $s->payrollRun->month) : collect(),
            'adjustments' => $officer ? PayAdjustment::with('employee')->where(compact('year', 'month'))->latest()->get() : collect(),
            'loans' => $officer ? Loan::with('employee')->orderByRaw("status = 'ACTIVE' desc")->latest()->get() : collect(),
            'employees' => $officer ? Employee::whereIn('status', PayrollService::PAID_STATUSES)->orderBy('first_name')->get() : collect(),
            'year' => $year,
            'month' => $month,
        ]);
    }

    public function show(PayrollRun $run)
    {
        $run->load(['payslips.employee.department']);

        return view('payroll.show', [
            'run' => $run,
            'slips' => $run->payslips->sortBy(fn ($s) => $s->employee?->last_name),
            'approver' => Access::hasRole(auth()->user(), self::APPROVERS),
        ]);
    }

    public function process(Request $request)
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'between:2020,2100'],
            'month' => ['required', 'integer', 'between:1,12'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $run = $this->payroll->process((int) $data['year'], (int) $data['month'], $request->user()->id, $data['notes'] ?? null);

        return redirect()->route('payroll.show', $run)->with('status', 'Payroll calculated for '.PayrollService::period($run->year, $run->month).'. It is waiting for approval.');
    }

    public function decide(Request $request, PayrollRun $run)
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['APPROVED', 'CANCELLED'])],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['decision'] === 'APPROVED'
            ? $this->payroll->approve($run, $request->user(), $data['note'] ?? null)
            : $this->payroll->cancel($run, $data['note'] ?? null);

        return redirect()->route('payroll.show', $run)->with('status', $data['decision'] === 'APPROVED' ? 'Payroll approved. Employees can now download their payslips.' : 'Payroll run cancelled.');
    }

    public function storeAdjustment(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'year' => ['required', 'integer', 'between:2020,2100'],
            'month' => ['required', 'integer', 'between:1,12'],
            'kind' => ['required', Rule::in(['BONUS', 'COMMISSION', 'ALLOWANCE', 'DEDUCTION'])],
            'label' => ['required', 'string', 'min:2', 'max:191'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'taxable' => ['nullable', 'boolean'],
        ]);
        $this->payroll->assertOpen((int) $data['year'], (int) $data['month']);
        $adjustment = PayAdjustment::create([...$data, 'taxable' => $request->boolean('taxable', true)]);
        Audit::log('CREATE', 'PayAdjustment', $adjustment->id, Format::title($data['kind']).' "'.$data['label'].'" for '.Format::fullName($adjustment->employee));
        $this->payroll->refresh((int) $data['year'], (int) $data['month'], 'Recalculated after a bonus or deduction.');

        return redirect()->route('payroll.index', ['year' => $data['year'], 'month' => $data['month']])->with('status', Format::title($data['kind']).' added for '.PayrollService::period((int) $data['year'], (int) $data['month']).'.');
    }

    public function destroyAdjustment(PayAdjustment $adjustment)
    {
        $this->payroll->assertOpen($adjustment->year, $adjustment->month);
        $adjustment->delete();
        Audit::log('DELETE', 'PayAdjustment', $adjustment->id, 'Removed "'.$adjustment->label.'"');
        $this->payroll->refresh($adjustment->year, $adjustment->month, 'Recalculated after a bonus or deduction was removed.');

        return back()->with('status', 'Adjustment removed.');
    }

    public function storeLoan(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'type' => ['required', 'string', 'min:2', 'max:191'],
            'principal' => ['required', 'numeric', 'gt:0'],
            'monthly_deduction' => ['required', 'numeric', 'gt:0', 'lte:principal'],
            'started_on' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], ['monthly_deduction.lte' => 'The monthly deduction cannot be more than the amount lent.']);
        $loan = Loan::create([...$data, 'balance' => $data['principal'], 'status' => 'ACTIVE']);
        Audit::log('CREATE', 'Loan', $loan->id, $data['type'].' for '.Format::fullName($loan->employee));
        $this->payroll->refresh(now()->year, now()->month, 'Recalculated after a loan or advance.');

        return redirect()->route('payroll.index')->with('status', 'Loan recorded. It is deducted from each payroll until repaid.');
    }

    public function payslip(Request $request, Payslip $payslip)
    {
        $user = $request->user();
        $payslip->load('employee.department', 'employee.jobTitle', 'payrollRun');
        abort_unless(Access::canSeeCompensation($user, $payslip->employee_id), 403, 'You cannot view this payslip.');
        abort_if($payslip->payrollRun->status !== PayrollStatus::APPROVED && ! Access::hasRole($user, self::OFFICERS), 403, 'This payslip is not approved yet.');

        $period = $payslip->payrollRun->year.'-'.str_pad((string) $payslip->payrollRun->month, 2, '0', STR_PAD_LEFT);

        return Pdf::loadView('pdf.payslip', ['slip' => $payslip, 'company' => Company::first()])
            ->setPaper('a4')
            ->download("payslip-{$payslip->employee->employee_no}-{$period}.pdf");
    }
}
