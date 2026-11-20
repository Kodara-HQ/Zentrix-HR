<?php

namespace App\Http\Controllers;

use App\Enums\ApprovalStatus;
use App\Enums\Role;
use App\Support\Tenant;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\EmployeeService;
use App\Services\LeaveService;
use App\Support\Access;
use App\Support\Format;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Leave page for HR, managers and ESS. Ported from the /leave routes and the React Leave page. */
class LeaveController extends Controller
{
    public function __construct(private LeaveService $leave, private EmployeeService $employees) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $own = $user->role === Role::EMPLOYEE;
        $visible = Access::scopeEmployees(Employee::query(), $user)->select('id');

        $requests = LeaveRequest::with('leaveType', 'employee')
            ->whereIn('employee_id', $visible)
            ->when($request->query('status'), fn ($q, $status) => $status === 'PENDING'
                ? $q->whereIn('status', [ApprovalStatus::PENDING_MANAGER, ApprovalStatus::PENDING_HR])
                : $q->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $balances = collect();
        $me = $user->employee_id ? Employee::find($user->employee_id) : null;
        if ($me) {
            $this->employees->grantLeaveBalances($me);
            $balances = LeaveBalance::with('leaveType')->where('employee_id', $me->id)->where('year', now()->year)->get()->sortBy('leaveType.name');
        }

        $calendar = $own ? collect() : LeaveRequest::with('leaveType', 'employee')
            ->whereIn('employee_id', $visible)
            ->where('status', ApprovalStatus::APPROVED)
            ->where('start_date', '<=', today()->addDays(31)->toDateString())
            ->where('end_date', '>=', today()->toDateString())
            ->orderBy('start_date')
            ->get();

        $applicants = $own ? collect() : Access::scopeEmployees(Employee::query(), $user)
            ->whereNotIn('status', ['INACTIVE', 'RESIGNED', 'RETIRED', 'TERMINATED'])
            ->orderBy('first_name')->get()
            ->filter(fn ($employee) => $this->leave->canApplyFor($user, $employee));

        return view('leave.index', [
            'own' => $own,
            'me' => $me,
            'requests' => $requests,
            'balances' => $balances,
            'calendar' => $calendar,
            'applicants' => $applicants,
            'types' => LeaveType::orderBy('name')->get()->filter(fn ($type) => ! $me || ! $own || ! $type->gender || $type->gender === $me->gender),
            'pendingCount' => LeaveRequest::whereIn('employee_id', $visible)->whereIn('status', [ApprovalStatus::PENDING_MANAGER, ApprovalStatus::PENDING_HR])->get()->filter(fn ($r) => $this->leave->canDecide($user, $r))->count(),
            'service' => $this->leave,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'employee_id' => ['nullable', 'exists:employees,id'],
            'leave_type_id' => ['required', 'exists:leave_types,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ], ['leave_type_id.required' => 'Choose a leave type.']);

        $employeeId = $user->role === Role::EMPLOYEE ? $user->employee_id : ($data['employee_id'] ?? $user->employee_id);
        abort_unless($employeeId, 422, 'Choose an employee.');

        $this->leave->apply($user, Employee::findOrFail($employeeId), LeaveType::findOrFail($data['leave_type_id']), $data['start_date'], $data['end_date'], $data['reason'] ?? null);

        return redirect()->route('leave.index')->with('status', 'Leave application submitted.');
    }

    public function decide(Request $request, LeaveRequest $leave)
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['APPROVED', 'REJECTED'])],
            'note' => ['nullable', 'required_if:decision,REJECTED', 'string', 'max:2000'],
        ], ['note.required_if' => 'Give a reason for the rejection.']);

        $this->leave->decide($request->user(), $leave, $data['decision'], $data['note'] ?? null);

        return back()->with('status', $data['decision'] === 'APPROVED' ? 'Leave approved.' : 'Leave rejected.');
    }

    public function cancel(Request $request, LeaveRequest $leave)
    {
        $this->leave->cancel($request->user(), $leave);

        return back()->with('status', 'Leave request cancelled.');
    }

    /** Printable leave form: blank with this year's balances, or filled in for one request. */
    public function form(Request $request)
    {
        $user = $request->user();
        $leave = $request->query('request') ? LeaveRequest::with('leaveType')->findOrFail($request->query('request')) : null;
        $employeeId = $leave?->employee_id ?? $request->query('employee') ?? $user->employee_id;
        abort_unless($employeeId, 422, 'This sign-in is not linked to an employee.');

        $employee = Employee::with('department', 'jobTitle', 'location')->findOrFail($employeeId);
        Access::assertEmployeeVisible($user, $employee);
        $this->employees->grantLeaveBalances($employee);

        $pdf = Pdf::loadView('pdf.leave-form', [
            'company' => Tenant::name(),
            'employee' => $employee,
            'balances' => LeaveBalance::with('leaveType')->where('employee_id', $employee->id)->where('year', now()->year)->get()->sortBy('leaveType.name'),
            'types' => LeaveType::orderBy('name')->get()->filter(fn ($type) => ! $type->gender || $type->gender === $employee->gender),
            'leave' => $leave,
        ])->setPaper('a4');

        return $pdf->stream("leave-form-{$employee->employee_no}.pdf");
    }
}
