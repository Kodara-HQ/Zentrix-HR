<?php

namespace App\Console\Commands;

use App\Enums\EventType;
use App\Enums\Role;
use App\Models\Certification;
use App\Models\Contract;
use App\Models\DisciplinaryCase;
use App\Models\Employee;
use App\Models\EmployeeEvent;
use App\Models\PerformanceCycle;
use App\Models\Separation;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Notifier;
use App\Support\Format;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Hourly reminders and housekeeping, ported from server/src/jobs/reminders.ts. Each reminder carries a
 * dedupe key, so running this every hour sends it once. (The monthly payroll is its own command.)
 */
class SendReminders extends Command
{
    protected $signature = 'hr:reminders';

    protected $description = 'Send upcoming-event reminders, confirm finished probations and complete due exits';

    public function handle(Notifier $notifier): int
    {
        $today = CarbonImmutable::today();
        $d = fn ($date) => CarbonImmutable::parse($date)->toDateString();
        $hr = User::where('is_active', true)->whereIn('role', [Role::SUPER_ADMIN, Role::HR_ADMIN, Role::HR_MANAGER])->pluck('id')->all();
        $sent = 0;
        $send = function (array $to, string $type, string $title, string $body, string $link, string $key) use ($notifier, &$sent) {
            $notifier->notify($to, $title, $body, $type, $link, $key);
            $sent++;
        };

        foreach (Employee::whereIn('status', ['ACTIVE', 'PROBATION', 'NOTICE'])->get() as $employee) {
            $next = CarbonImmutable::parse($employee->date_of_birth)->setYear($today->year);
            if ($next->lt($today)) {
                $next = $next->addYear();
            }
            if ($today->diffInDays($next) <= 7) {
                $send($hr, 'BIRTHDAY', 'Upcoming birthday', Format::fullName($employee).' has a birthday on '.Format::date($next).'.', "/employees/{$employee->id}", "birthday:{$employee->id}:{$next->toDateString()}");
            }
        }

        foreach (Contract::with('employee')->where('status', 'ACTIVE')->whereBetween('end_date', [$today->toDateString(), $today->addDays(60)->toDateString()])->get() as $contract) {
            $send($hr, 'CONTRACT_EXPIRY', 'Contract nearing expiration', Format::fullName($contract->employee)." — {$contract->title} ends on ".Format::date($contract->end_date).'.', "/employees/{$contract->employee_id}?tab=documents", "contract:{$contract->id}:{$d($contract->end_date)}");
        }

        foreach (Employee::where('status', 'PROBATION')->whereBetween('probation_end_date', [$today->toDateString(), $today->addDays(14)->toDateString()])->get() as $employee) {
            $send($hr, 'PROBATION', 'Probation ending', Format::fullName($employee).' completes probation on '.Format::date($employee->probation_end_date).'.', "/employees/{$employee->id}", "probation:{$employee->id}");
        }

        foreach (Certification::with('employee')->whereBetween('expires_on', [$today->toDateString(), $today->addDays(30)->toDateString()])->get() as $certificate) {
            $send($hr, 'CERTIFICATION', 'Certification expiring', "{$certificate->name} for ".Format::fullName($certificate->employee).' expires on '.Format::date($certificate->expires_on).'.', '/training', "cert:{$certificate->id}");
        }

        foreach (TrainingProgram::with('enrollments')->whereIn('status', ['PLANNED', 'ONGOING'])->whereBetween('start_date', [$today->toDateString(), $today->addDays(3)->toDateString()])->get() as $program) {
            $enrolled = User::whereIn('employee_id', $program->enrollments->pluck('employee_id'))->pluck('id')->all();
            $send([...$hr, ...$enrolled], 'TRAINING', 'Training coming up', "{$program->title} starts on ".Format::date($program->start_date).'.', '/training', "training:{$program->id}");
        }

        foreach (DisciplinaryCase::whereIn('status', ['OPEN', 'INVESTIGATING', 'HEARING'])->whereBetween('hearing_at', [$today, $today->addDays(3)->endOfDay()])->get() as $case) {
            $send($hr, 'HEARING', 'Disciplinary hearing', "Case {$case->case_no} is scheduled for ".CarbonImmutable::parse($case->hearing_at)->format('j M Y H:i').'.', '/relations', "hearing:{$case->id}:{$case->hearing_at}");
        }

        foreach (PerformanceCycle::where('status', 'OPEN')->whereBetween('end_date', [$today->toDateString(), $today->addDays(14)->toDateString()])->get() as $cycle) {
            $send($hr, 'APPRAISAL', 'Appraisal deadline', "{$cycle->name} closes on ".Format::date($cycle->end_date).'.', '/performance', "cycle:{$cycle->id}");
        }

        // Approved exits whose last working day has passed: end employment and switch off the sign-in.
        $exits = 0;
        foreach (Separation::with('employee')->where('status', 'APPROVED')->where('last_working_day', '<=', $today->toDateString())->get() as $separation) {
            DB::transaction(function () use ($separation) {
                $status = match ($separation->type->value) { 'RETIREMENT' => 'RETIRED', 'TERMINATION' => 'TERMINATED', default => 'RESIGNED' };
                if ($separation->employee->status->value !== $status) {
                    $separation->employee->update(['status' => $status]);
                    EmployeeEvent::create(['employee_id' => $separation->employee_id, 'type' => EventType::STATUS_CHANGE, 'title' => 'Employment ended',
                        'description' => $separation->reason, 'effective_date' => $separation->last_working_day, 'to_value' => ['status' => $status]]);
                    User::where('employee_id', $separation->employee_id)->update(['is_active' => false]);
                }
                $separation->update(['status' => 'COMPLETED']);
            });
            $exits++;
        }

        // Probation that ended before today: confirm the employee.
        $confirmed = 0;
        foreach (Employee::where('status', 'PROBATION')->where('probation_end_date', '<', $today->toDateString())->get() as $employee) {
            DB::transaction(function () use ($employee, $today) {
                $employee->update(['status' => 'ACTIVE']);
                EmployeeEvent::create(['employee_id' => $employee->id, 'type' => EventType::CONFIRMATION, 'title' => 'Probation completed',
                    'effective_date' => $employee->probation_end_date ?? $today, 'to_value' => ['status' => 'ACTIVE']]);
            });
            $confirmed++;
        }

        $this->info("Checked {$sent} reminders, completed {$exits} exits, confirmed {$confirmed} probations.");

        return self::SUCCESS;
    }
}
