<?php

namespace App\Http\Controllers;

use App\Enums\EventType;
use App\Enums\LetterType;
use App\Enums\Role;
use App\Models\ClearanceItem;
use App\Models\Employee;
use App\Models\Separation;
use App\Models\User;
use App\Services\EmployeeService;
use App\Services\Letters;
use App\Services\Notifier;
use App\Support\Access;
use App\Support\Audit;
use App\Support\Format;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Resignations, retirements, terminations and contract ends, with the clearance checklist.
 * Ported from server/src/routes/workplace.ts. An approved exit puts the person on notice; on the last
 * working day (here, or in the hourly hr:reminders job) employment ends and the sign-in is switched off.
 */
class ExitController extends Controller
{
    public const CLEARANCE = ['Staff identity card', 'Laptop and accessories', 'Access card and keys', 'Mobile device', 'Outstanding accountabilities'];

    public function __construct(private Notifier $notifier) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $visible = Access::scopeEmployees(Employee::query(), $user)->select('id');

        return view('exit.index', [
            'hr' => Access::isHr($user),
            'user' => $user,
            'exits' => Separation::with('employee', 'clearance')->whereIn('employee_id', $visible)->latest()->get(),
            'people' => Access::isHr($user) ? Employee::whereIn('status', ['ACTIVE', 'PROBATION', 'NOTICE', 'SUSPENDED'])->orderBy('first_name')->get()
                ->mapWithKeys(fn ($e) => [$e->id => $e->employee_no.' · '.Format::fullName($e)])->all() : [],
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $hr = Access::isHr($user);
        $data = $request->validate([
            'employee_id' => ['nullable', 'exists:employees,id'],
            'type' => ['required', Rule::in($hr ? ['RESIGNATION', 'RETIREMENT', 'TERMINATION', 'END_OF_CONTRACT'] : ['RESIGNATION'])],
            'reason' => ['required', 'string', 'min:5', 'max:3000'],
            'notice_date' => ['required', 'date'],
            'last_working_day' => ['required', 'date', 'after_or_equal:notice_date'],
            'interview_notes' => ['nullable', 'string', 'max:5000'],
        ], ['last_working_day.after_or_equal' => 'The last working day cannot be before the notice date.', 'type.in' => 'Only HR can record that kind of exit.']);
        $employeeId = $hr ? ($data['employee_id'] ?? $user->employee_id) : $user->employee_id;
        abort_unless($employeeId, 422, 'This sign-in is not linked to an employee.');
        if (Separation::where('employee_id', $employeeId)->whereIn('status', ['PENDING', 'APPROVED'])->exists()) {
            throw ValidationException::withMessages(['employee_id' => 'There is already an exit in progress for this employee.']);
        }

        $exit = DB::transaction(function () use ($data, $employeeId) {
            $exit = Separation::create([...$data, 'employee_id' => $employeeId, 'status' => 'PENDING']);
            foreach (self::CLEARANCE as $item) {
                $exit->clearance()->create(['item' => $item, 'returned' => false]);
            }

            return $exit;
        });
        $this->notifier->notify(User::where('is_active', true)->whereIn('role', [Role::SUPER_ADMIN, Role::HR_ADMIN, Role::HR_MANAGER])->pluck('id')->all(),
            'Separation submitted', Format::title($data['type']).' — '.Format::fullName(Employee::find($employeeId)), 'SEPARATION', '/exit');
        Audit::log('CREATE', 'Separation', $exit->id, Format::title($data['type']).' recorded');

        return back()->with('status', $hr ? 'Exit recorded.' : 'Your resignation has been sent to HR.');
    }

    public function decide(Request $request, Separation $separation, Letters $letters, EmployeeService $employees)
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['APPROVED', 'REJECTED'])],
            'note' => ['nullable', 'string', 'max:3000'],
            'final_pay_notes' => ['nullable', 'string', 'max:3000'],
        ]);
        abort_unless($separation->status->value === 'PENDING', 422, 'This exit has already been decided.');
        $employee = $separation->employee;

        if ($data['decision'] === 'REJECTED') {
            $separation->update(['status' => 'REJECTED', 'decision_note' => $data['note'] ?? null]);
            Audit::log('UPDATE', 'Separation', $separation->id, 'Rejected exit for '.Format::fullName($employee));

            return back()->with('status', 'Exit rejected.');
        }

        $due = CarbonImmutable::parse($separation->last_working_day)->lte(today());
        $status = $due ? match ($separation->type->value) { 'RETIREMENT' => 'RETIRED', 'TERMINATION' => 'TERMINATED', default => 'RESIGNED' } : 'NOTICE';
        DB::transaction(function () use ($separation, $employee, $employees, $status, $due, $data) {
            $employee->update(['status' => $status]);
            $employees->recordEvent($employee, EventType::STATUS_CHANGE, $due ? 'Employment ended' : 'Notice period started',
                $due ? CarbonImmutable::parse($separation->last_working_day)->toDateString() : today()->toDateString(), $separation->reason, to: ['status' => $status]);
            if ($due) {
                User::where('employee_id', $employee->id)->update(['is_active' => false]);
            }
            $separation->update(['status' => $due ? 'COMPLETED' : 'APPROVED', 'decision_note' => $data['note'] ?? null, 'final_pay_notes' => $data['final_pay_notes'] ?? null]);
        });
        if ($separation->type->value === 'TERMINATION') {
            $letters->issue($employee, LetterType::TERMINATION, 'Termination letter', today()->toDateString(),
                [$separation->reason, 'Your last working day is '.Format::date($separation->last_working_day).'.', $data['note'] ?? 'Please complete the clearance checklist with Human Resources.']);
        }
        Audit::log('UPDATE', 'Separation', $separation->id, 'Approved exit for '.Format::fullName($employee));

        return back()->with('status', $due ? 'Exit approved and employment ended.' : 'Exit approved. '.$employee->first_name.' is on notice until '.Format::date($separation->last_working_day).'.');
    }

    public function clear(Request $request, ClearanceItem $item)
    {
        $item->update(['returned' => $request->boolean('returned'), 'cleared_by_id' => $request->user()->id, 'notes' => $request->input('notes')]);

        return back();
    }
}
