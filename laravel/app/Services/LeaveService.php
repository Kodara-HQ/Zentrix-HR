<?php

namespace App\Services;

use App\Enums\ApprovalStatus;
use App\Enums\Role;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Models\WorkflowConfig;
use App\Support\Access;
use App\Support\Audit;
use App\Support\Format;
use App\Support\WorkCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Leave applications and approvals, ported from the /leave routes in server/src/routes/time.ts.
 * Days are reserved as "pending" on application, moved to "used" on final approval, and released on
 * rejection or cancellation.
 */
class LeaveService
{
    public const OPEN = [ApprovalStatus::PENDING_MANAGER, ApprovalStatus::PENDING_HR, ApprovalStatus::APPROVED];

    public function __construct(private EmployeeService $employees, private Notifier $notifier) {}

    /** @return string[] e.g. ['MANAGER', 'HR'] */
    public function steps(): array
    {
        $steps = WorkflowConfig::where('key', 'LEAVE')->value('steps');
        $steps = is_string($steps) ? json_decode($steps, true) : $steps;

        return $steps ?: ['MANAGER', 'HR'];
    }

    public function canApplyFor(User $user, Employee $employee): bool
    {
        return $user->employee_id === $employee->id || Access::isHr($user) || Access::canApproveAsManager($user, $employee);
    }

    /** Whether this user may approve or reject the request at its current step. Nobody decides their own leave. */
    public function canDecide(User $user, LeaveRequest $request): bool
    {
        if ($user->employee_id && $user->employee_id === $request->employee_id) {
            return false;
        }

        return match ($request->status) {
            ApprovalStatus::PENDING_MANAGER => Access::canApproveAsManager($user, $request->employee),
            ApprovalStatus::PENDING_HR => Access::isHr($user),
            default => false,
        };
    }

    public function canCancel(User $user, LeaveRequest $request): bool
    {
        return in_array($request->status, self::OPEN, true)
            && ($user->employee_id === $request->employee_id || Access::isHr($user));
    }

    public function apply(User $user, Employee $employee, LeaveType $type, string $start, string $end, ?string $reason): LeaveRequest
    {
        $fail = fn (string $field, string $message) => throw ValidationException::withMessages([$field => $message]);

        abort_unless($this->canApplyFor($user, $employee), 403, 'You cannot apply for this employee.');
        $gender = $type->gender?->value;
        if ($gender && $gender !== $employee->gender->value) {
            $fail('leave_type_id', "{$type->name} does not apply to this employee.");
        }
        if ($type->requires_reason && ! trim((string) $reason)) {
            $fail('reason', "Give a reason for {$type->name}.");
        }

        $start = CarbonImmutable::parse($start)->startOfDay();
        $end = CarbonImmutable::parse($end)->startOfDay();
        if ($end->lt($start)) {
            $fail('end_date', 'The end date is before the start date.');
        }
        $days = WorkCalendar::countDays($start, $end, $type->count_mode->value);
        if ($days <= 0) {
            $fail('end_date', 'The selected dates contain no leave days.');
        }

        $overlap = LeaveRequest::where('employee_id', $employee->id)
            ->whereIn('status', self::OPEN)
            ->where('start_date', '<=', $end->toDateString())
            ->where('end_date', '>=', $start->toDateString())
            ->exists();
        if ($overlap) {
            $fail('start_date', 'These dates overlap an existing leave request.');
        }

        $this->employees->grantLeaveBalances($employee, $start->year);
        $steps = $this->steps();
        $status = ($steps[0] ?? 'HR') === 'HR' || ! $employee->manager_id ? ApprovalStatus::PENDING_HR : ApprovalStatus::PENDING_MANAGER;

        $request = DB::transaction(function () use ($employee, $type, $start, $end, $days, $reason, $status, $fail) {
            if ($type->affects_balance) {
                $balance = $this->balance($employee->id, $type->id, $start->year);
                $remaining = (int) round($balance ? $balance->entitled - $balance->used - $balance->pending : 0);
                if ($remaining < $days) {
                    $fail('end_date', "Only {$remaining} ".str('day')->plural($remaining)." remain for {$type->name}.");
                }
                $balance->increment('pending', $days);
            }

            return LeaveRequest::create([
                'employee_id' => $employee->id,
                'leave_type_id' => $type->id,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'days' => $days,
                'reason' => $reason ?: null,
                'status' => $status,
                'approvals' => [],
            ]);
        });

        $recipients = $status === ApprovalStatus::PENDING_HR
            ? $this->hrUserIds()
            : [User::where('employee_id', $employee->manager_id)->value('id')];
        $this->notifier->notify($recipients, 'Leave application submitted', Format::fullName($employee)." requested {$days} ".str('day')->plural($days)." of {$type->name}.", 'LEAVE', '/leave');
        Audit::log('CREATE', 'LeaveRequest', $request->id, 'Applied for '.$type->name.' for '.Format::fullName($employee));

        return $request;
    }

    public function decide(User $user, LeaveRequest $request, string $decision, ?string $note): void
    {
        $request->load('employee', 'leaveType');
        abort_if(! in_array($request->status, [ApprovalStatus::PENDING_MANAGER, ApprovalStatus::PENDING_HR], true), 422, 'This request is already closed.');
        abort_unless($this->canDecide($user, $request), 403, $request->status === ApprovalStatus::PENDING_MANAGER
            ? 'A manager for this employee must review the request first.'
            : 'Human Resources must complete this approval.');

        $approvals = $request->approvals ?? [];
        $approvals[] = ['by' => $user->id, 'role' => $user->role->value, 'decision' => $decision, 'note' => $note, 'at' => now()->toIso8601String()];
        $account = User::where('employee_id', $request->employee_id)->value('id');
        $name = Format::fullName($request->employee);

        if ($decision === 'REJECTED') {
            DB::transaction(function () use ($request, $note, $approvals) {
                $this->release($request, 'pending');
                $request->update(['status' => ApprovalStatus::REJECTED, 'decision_note' => $note, 'approvals' => $approvals]);
            });
            $this->notifier->notify([$account], 'Leave rejected', $note ?: 'Your leave request was rejected.', 'LEAVE', '/leave');
            Audit::log('UPDATE', 'LeaveRequest', $request->id, "Rejected leave for {$name}");

            return;
        }

        if ($request->status === ApprovalStatus::PENDING_MANAGER && in_array('HR', $this->steps(), true) && ! Access::isHr($user)) {
            $request->update(['status' => ApprovalStatus::PENDING_HR, 'approvals' => $approvals]);
            $this->notifier->notify($this->hrUserIds(), 'Leave awaiting HR approval', "{$name} — {$request->leaveType->name}.", 'LEAVE', '/leave');
            Audit::log('UPDATE', 'LeaveRequest', $request->id, "Manager approved leave for {$name}");

            return;
        }

        DB::transaction(function () use ($request, $note, $approvals) {
            if ($request->leaveType->affects_balance) {
                $balance = $this->balance($request->employee_id, $request->leave_type_id, $request->start_date->year);
                $balance?->update(['pending' => max(0, $balance->pending - $request->days), 'used' => $balance->used + $request->days]);
            }
            $request->update(['status' => ApprovalStatus::APPROVED, 'decision_note' => $note, 'approvals' => $approvals]);
        });
        $this->notifier->notify([$account], 'Leave approved', "{$request->leaveType->name} from {$request->start_date->toDateString()} to {$request->end_date->toDateString()} is approved.", 'LEAVE', '/leave');
        Audit::log('UPDATE', 'LeaveRequest', $request->id, "Approved leave for {$name}");
    }

    public function cancel(User $user, LeaveRequest $request): void
    {
        $request->load('leaveType', 'employee');
        abort_unless($this->canCancel($user, $request), 403, 'You cannot cancel this request.');

        DB::transaction(function () use ($request) {
            $this->release($request, $request->status === ApprovalStatus::APPROVED ? 'used' : 'pending');
            $request->update(['status' => ApprovalStatus::CANCELLED]);
        });
        Audit::log('UPDATE', 'LeaveRequest', $request->id, 'Cancelled leave for '.Format::fullName($request->employee));
    }

    /** Gives back the request's days from the pending or used column of its balance. */
    private function release(LeaveRequest $request, string $column): void
    {
        if (! $request->leaveType->affects_balance) {
            return;
        }
        $balance = $this->balance($request->employee_id, $request->leave_type_id, $request->start_date->year);
        $balance?->update([$column => max(0, $balance->{$column} - $request->days)]);
    }

    /** The balance row, locked for the rest of the transaction so two requests cannot spend the same days. */
    private function balance(string $employeeId, string $typeId, int $year): ?LeaveBalance
    {
        return LeaveBalance::where(['employee_id' => $employeeId, 'leave_type_id' => $typeId, 'year' => $year])->lockForUpdate()->first();
    }

    private function hrUserIds(): array
    {
        return User::where('is_active', true)->whereIn('role', [Role::SUPER_ADMIN, Role::HR_ADMIN, Role::HR_MANAGER])->pluck('id')->all();
    }
}
