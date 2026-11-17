<?php

namespace App\Services;

use App\Enums\ApprovalStatus;
use App\Enums\Role;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\OvertimeRequest;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\WorkingCalendar;
use App\Support\Access;
use App\Support\Audit;
use App\Support\Format;
use App\Support\PayrollMath;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Fingerprint punches, manual entries, corrections and overtime. Ported from server/src/routes/time.ts.
 * Times are stored as Ghana local time (UTC+0, no daylight saving), as in the Node version.
 */
class AttendanceService
{
    /** A scan after the shift ends by at least this many minutes becomes an overtime request. */
    public const AUTO_OVERTIME_MINUTES = 30;

    public function __construct(private Notifier $notifier) {}

    /** The working calendar, overridden by the employee's shift on that day if they have one. */
    public function shiftFor(string $employeeId, CarbonImmutable $day): array
    {
        $calendar = WorkingCalendar::where('is_active', true)->first();
        $shift = [
            'start' => $calendar?->start_time ?? '08:00',
            'end' => $calendar?->end_time ?? '17:00',
            'grace' => $calendar?->grace_minutes ?? 15,
            'work_days' => $calendar?->work_days ?: [1, 2, 3, 4, 5],
            'overtime_weekday' => (float) ($calendar?->overtime_weekday ?? 1.5),
            'overtime_weekend' => (float) ($calendar?->overtime_weekend ?? 2),
        ];

        $assignment = ShiftAssignment::with('shift')->where('employee_id', $employeeId)
            ->where('effective_from', '<=', $day->toDateString())
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $day->toDateString()))
            ->orderByDesc('effective_from')->first();
        if ($assignment) {
            $shift = [...$shift, 'start' => $assignment->shift->start_time, 'end' => $assignment->shift->end_time,
                'grace' => $assignment->shift->grace_minutes, 'work_days' => $assignment->shift->work_days];
        }

        return $shift;
    }

    private static function at(CarbonImmutable $day, string $time): CarbonImmutable
    {
        [$h, $m] = array_map('intval', explode(':', $time)) + [0, 0];

        return $day->setTime($h, $m);
    }

    /** Minutes late after the grace period (0 when on time). */
    public static function lateMinutes(CarbonImmutable $clockIn, CarbonImmutable $expected, int $grace): int
    {
        $diff = (int) round(($clockIn->getTimestamp() - $expected->getTimestamp()) / 60);

        return $diff > $grace ? $diff : 0;
    }

    /**
     * Records a scan. The first scan of the day is clock-in; any later one moves clock-out.
     * Leaving 30+ minutes after the shift ends opens (or updates) one overtime request for the day.
     */
    public function punch(Employee $employee, CarbonImmutable $when, string $source, ?string $notes = null, ?string $ip = null): AttendanceRecord
    {
        $day = $when->startOfDay();
        $shift = $this->shiftFor($employee->id, $day);

        return DB::transaction(function () use ($employee, $when, $day, $shift, $source, $notes, $ip) {
            $record = AttendanceRecord::where('employee_id', $employee->id)->where('date', $day->toDateString())->lockForUpdate()->first();

            if (! $record || ! $record->clock_in) {
                $late = self::lateMinutes($when, self::at($day, $shift['start']), $shift['grace']);
                $record ??= new AttendanceRecord(['employee_id' => $employee->id, 'date' => $day->toDateString()]);
                $record->fill(['clock_in' => $when, 'status' => $late ? 'LATE' : 'PRESENT', 'late_minutes' => $late, 'source' => $source, 'notes' => $notes, 'clock_in_ip' => $ip])->save();

                return $record;
            }

            $expectedOut = self::at($day, $shift['end']);
            $diff = (int) round(($when->getTimestamp() - $expectedOut->getTimestamp()) / 60);
            $record->update([
                'clock_out' => $when,
                'early_departure_minutes' => max(0, -$diff),
                'overtime_minutes' => $diff > 15 ? $diff : 0,
                'source' => $source,
                'notes' => $notes ?: $record->notes,
                'clock_out_ip' => $ip,
            ]);

            if ($diff >= self::AUTO_OVERTIME_MINUTES) {
                $weekend = ! in_array($day->dayOfWeek, $shift['work_days'], true);
                OvertimeRequest::updateOrCreate(
                    ['employee_id' => $employee->id, 'date' => $day->toDateString(), 'status' => ApprovalStatus::PENDING_MANAGER, 'reason' => 'Recorded from clock-out beyond the scheduled end time.'],
                    ['hours' => PayrollMath::round2($diff / 60), 'multiplier' => $weekend ? $shift['overtime_weekend'] : $shift['overtime_weekday']],
                );
            }

            return $record;
        });
    }

    public function manual(User $user, Employee $employee, string $date, string $status, ?string $in, ?string $out, ?string $notes): AttendanceRecord
    {
        Access::assertEmployeeVisible($user, $employee);
        $day = CarbonImmutable::parse($date)->startOfDay();
        $record = AttendanceRecord::updateOrCreate(
            ['employee_id' => $employee->id, 'date' => $day->toDateString()],
            ['status' => $status, 'clock_in' => $in ? self::at($day, $in) : null, 'clock_out' => $out ? self::at($day, $out) : null,
                'late_minutes' => 0, 'notes' => $notes, 'source' => 'MANUAL'],
        );
        Audit::log('UPDATE', 'Attendance', $record->id, 'Attendance for '.Format::fullName($employee)." recorded for {$day->toDateString()}");

        return $record;
    }

    /** Managers decide for their people, HR for anyone; nobody decides their own. */
    public function canDecide(User $user, Employee $employee): bool
    {
        if ($user->employee_id && $user->employee_id === $employee->id) {
            return false;
        }

        return Access::isHr($user) || Access::canApproveAsManager($user, $employee);
    }

    public function requestCorrection(User $user, Employee $employee, string $date, string $reason, ?string $in, ?string $out): AttendanceCorrection
    {
        abort_unless($user->employee_id === $employee->id || Access::isHr($user) || Access::canApproveAsManager($user, $employee), 403, 'You cannot request a correction for this employee.');
        $day = CarbonImmutable::parse($date)->startOfDay();
        if ($day->isAfter(today())) {
            throw ValidationException::withMessages(['date' => 'A correction can only be for today or an earlier day.']);
        }

        $correction = AttendanceCorrection::create([
            'employee_id' => $employee->id,
            'attendance_id' => AttendanceRecord::where('employee_id', $employee->id)->where('date', $day->toDateString())->value('id'),
            'date' => $day->toDateString(),
            'reason' => $reason,
            'requested_clock_in' => $in ? self::at($day, $in) : null,
            'requested_clock_out' => $out ? self::at($day, $out) : null,
            'status' => ApprovalStatus::PENDING_MANAGER,
        ]);
        $this->notifier->notify([User::where('employee_id', $employee->manager_id)->value('id'), ...$this->hrUserIds()],
            'Attendance correction requested', Format::fullName($employee)." requested a correction for {$day->toDateString()}.", 'ATTENDANCE', '/attendance');

        return $correction;
    }

    public function decideCorrection(User $user, AttendanceCorrection $correction, string $decision, ?string $note): void
    {
        $correction->load('employee');
        abort_unless(in_array($correction->status, [ApprovalStatus::PENDING_MANAGER, ApprovalStatus::PENDING_HR], true), 422, 'This request is already closed.');
        abort_unless($this->canDecide($user, $correction->employee), 403, 'You cannot review this request.');

        DB::transaction(function () use ($correction, $decision, $note) {
            if ($decision === 'APPROVED') {
                $day = CarbonImmutable::parse($correction->date)->startOfDay();
                $shift = $this->shiftFor($correction->employee_id, $day);
                $in = $correction->requested_clock_in ? CarbonImmutable::parse($correction->requested_clock_in) : null;
                $late = $in ? self::lateMinutes($in, self::at($day, $shift['start']), $shift['grace']) : 0;
                AttendanceRecord::updateOrCreate(
                    ['employee_id' => $correction->employee_id, 'date' => $day->toDateString()],
                    ['clock_in' => $in, 'clock_out' => $correction->requested_clock_out, 'status' => $late ? 'LATE' : 'PRESENT',
                        'late_minutes' => $late, 'source' => 'MANUAL', 'notes' => $correction->reason],
                );
            }
            $correction->update(['status' => $decision, 'decision_note' => $note]);
        });

        $this->notifier->notify([User::where('employee_id', $correction->employee_id)->value('id')],
            'Attendance correction '.strtolower($decision), $note ?: $correction->reason, 'ATTENDANCE', '/attendance');
        Audit::log('UPDATE', 'AttendanceCorrection', $correction->id, ucfirst(strtolower($decision)).' correction for '.Format::fullName($correction->employee));
    }

    public function requestOvertime(User $user, Employee $employee, string $date, float $hours, string $reason): OvertimeRequest
    {
        abort_unless($user->employee_id === $employee->id || $this->canDecide($user, $employee), 403, 'You cannot request overtime for this employee.');
        $day = CarbonImmutable::parse($date)->startOfDay();
        $shift = $this->shiftFor($employee->id, $day);
        $weekend = ! in_array($day->dayOfWeek, $shift['work_days'], true);

        $overtime = OvertimeRequest::create([
            'employee_id' => $employee->id, 'date' => $day->toDateString(), 'hours' => $hours, 'reason' => $reason,
            'multiplier' => $weekend ? $shift['overtime_weekend'] : $shift['overtime_weekday'], 'status' => ApprovalStatus::PENDING_MANAGER,
        ]);
        $this->notifier->notify([User::where('employee_id', $employee->manager_id)->value('id')], 'Overtime requested', Format::fullName($employee)." asked for {$hours} hours of overtime on {$day->toDateString()}.", 'ATTENDANCE', '/attendance');

        return $overtime;
    }

    public function decideOvertime(User $user, OvertimeRequest $overtime, string $decision, ?string $note): void
    {
        $overtime->load('employee');
        abort_unless(in_array($overtime->status, [ApprovalStatus::PENDING_MANAGER, ApprovalStatus::PENDING_HR], true), 422, 'This request is already closed.');
        abort_unless($this->canDecide($user, $overtime->employee), 403, 'You cannot review this request.');

        $overtime->update(['status' => $decision, 'decision_note' => $note]);
        $this->notifier->notify([User::where('employee_id', $overtime->employee_id)->value('id')], 'Overtime '.strtolower($decision), $note ?: "{$overtime->hours} hours on {$overtime->date->toDateString()}.", 'ATTENDANCE', '/attendance');
        Audit::log('UPDATE', 'OvertimeRequest', $overtime->id, ucfirst(strtolower($decision)).' overtime for '.Format::fullName($overtime->employee));
    }

    private function hrUserIds(): array
    {
        return User::where('is_active', true)->whereIn('role', [Role::SUPER_ADMIN, Role::HR_ADMIN, Role::HR_MANAGER])->pluck('id')->all();
    }
}
