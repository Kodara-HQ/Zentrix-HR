<?php

namespace App\Services;

use App\Enums\PayrollStatus;
use App\Enums\Role;
use App\Models\Employee;
use App\Models\Loan;
use App\Models\OvertimeRequest;
use App\Models\PayAdjustment;
use App\Models\PayrollComponent;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\Separation;
use App\Models\StatutoryConfig;
use App\Models\User;
use App\Models\WorkingCalendar;
use App\Support\Audit;
use App\Support\PayrollMath;
use App\Support\WorkCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Monthly payroll runs, ported from server/src/routes/payroll.ts.
 *
 * Differences from the Node version, on purpose:
 * - An approved month is final. Bonuses, deductions and loans for it are refused instead of silently
 *   recalculating payslips employees may already have downloaded.
 * - The automatic monthly run (php artisan hr:payroll-month) waits for HR approval instead of approving
 *   itself, so loan balances are always reduced by the approval step.
 */
class PayrollService
{
    public const PAID_STATUSES = ['PROBATION', 'ACTIVE', 'NOTICE', 'SUSPENDED'];

    public function __construct(private Notifier $notifier) {}

    public static function period(int $year, int $month): string
    {
        return CarbonImmutable::create($year, $month, 1)->format('F Y');
    }

    /** Statutory rates and the standard pay components, created on first use (ensureStatutoryPayroll in Node). */
    public function ensureSetup(): void
    {
        if (! StatutoryConfig::where('kind', 'GHANA_PAYROLL')->exists()) {
            StatutoryConfig::create([
                'name' => 'Ghana payroll before September 2026', 'kind' => 'GHANA_PAYROLL',
                'effective_from' => '2024-01-01', 'effective_to' => '2026-08-31',
                'employee_rate' => 0.055, 'employer_rate' => 0.13, 'tier1_employer_rate' => 0.08, 'tier2_rate' => 0.05,
                'bands' => PayrollMath::PREVIOUS_PAYE_BANDS,
                'notes' => 'Monthly resident bands in force before the Income Tax (Amendment) Act, 2026 (Act 1178).',
            ]);
            StatutoryConfig::create([
                'name' => 'Ghana payroll from September 2026', 'kind' => 'GHANA_PAYROLL', 'effective_from' => '2026-09-01',
                'employee_rate' => 0.055, 'employer_rate' => 0.13, 'tier1_employer_rate' => 0.08, 'tier2_rate' => 0.05,
                'bands' => PayrollMath::CURRENT_PAYE_BANDS,
                'notes' => 'GRA monthly bands effective 1 September 2026. Employee SSNIT is 5.5% of basic salary. Employer SSNIT is 13%, of which 5% is Tier 2.',
            ]);
        }
        foreach ([
            ['name' => 'Transport allowance', 'code' => 'TRANSPORT', 'kind' => 'ALLOWANCE', 'taxable' => true],
            ['name' => 'Rent allowance', 'code' => 'RENT', 'kind' => 'ALLOWANCE', 'taxable' => true],
            ['name' => 'Staff welfare deduction', 'code' => 'WELFARE', 'kind' => 'DEDUCTION', 'taxable' => false, 'description' => 'Post-tax welfare contribution'],
        ] as $component) {
            PayrollComponent::firstOrCreate(['code' => $component['code']], $component);
        }
    }

    public function rates(int $year, int $month): array
    {
        $on = CarbonImmutable::create($year, $month, 15)->toDateString();
        $config = StatutoryConfig::where('kind', 'GHANA_PAYROLL')
            ->where('effective_from', '<=', $on)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $on))
            ->orderByDesc('effective_from')
            ->first();
        if (! $config?->bands) {
            throw ValidationException::withMessages(['month' => 'No statutory payroll rates are effective for this month.']);
        }

        return [
            'employee_rate' => (float) $config->employee_rate,
            'employer_rate' => (float) $config->employer_rate,
            'tier1_employer_rate' => (float) $config->tier1_employer_rate,
            'tier2_rate' => (float) $config->tier2_rate,
            'bands' => $config->bands,
        ];
    }

    public function assertOpen(int $year, int $month): void
    {
        if (PayrollRun::where(compact('year', 'month'))->where('status', PayrollStatus::APPROVED)->exists()) {
            throw ValidationException::withMessages(['month' => self::period($year, $month).' is already approved, so its payslips are final. Add this to the next month instead.']);
        }
    }

    /** Calculates every payslip for the month and saves the run as waiting for approval. */
    public function process(int $year, int $month, ?string $userId = null, ?string $notes = null): PayrollRun
    {
        $this->ensureSetup();
        $this->assertOpen($year, $month);
        $rates = $this->rates($year, $month);

        $monthStart = CarbonImmutable::create($year, $month, 1);
        $monthEnd = $monthStart->endOfMonth()->startOfDay();
        $workDays = WorkCalendar::workDays();
        $holidays = WorkCalendar::holidays($monthStart, $monthEnd);
        $monthDays = WorkCalendar::countDays($monthStart, $monthEnd, 'WORKING_DAYS', $workDays, $holidays);
        $hoursPerMonth = (float) (WorkingCalendar::where('is_active', true)->value('hours_per_month') ?: 176);

        $employees = Employee::with(['payItems.component', 'loans' => fn ($q) => $q->where('status', 'ACTIVE')])
            ->whereIn('status', self::PAID_STATUSES)
            ->where('date_of_employment', '<=', $monthEnd->toDateString())
            ->get();
        $adjustments = PayAdjustment::where(compact('year', 'month'))->get()->groupBy('employee_id');
        $overtime = OvertimeRequest::where('status', 'APPROVED')->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()])->get()->groupBy('employee_id');
        $lastDays = Separation::whereIn('status', ['APPROVED', 'COMPLETED'])->whereBetween('last_working_day', [$monthStart->toDateString(), $monthEnd->toDateString()])->pluck('last_working_day', 'employee_id');

        $slips = [];
        foreach ($employees as $employee) {
            $start = $employee->date_of_employment->gt($monthStart) ? CarbonImmutable::parse($employee->date_of_employment) : $monthStart;
            $end = isset($lastDays[$employee->id]) ? CarbonImmutable::parse($lastDays[$employee->id]) : $monthEnd;
            $factor = PayrollMath::proRate(WorkCalendar::countDays($start, $end, 'WORKING_DAYS', $workDays, $holidays), $monthDays);
            $basic = PayrollMath::round2((float) $employee->basic_salary * $factor);

            $in = ['basic' => $basic, 'taxable_allowances' => 0, 'non_taxable_allowances' => 0, 'overtime' => 0, 'bonus' => 0, 'commission' => 0, 'relief' => 0, 'post_tax_deductions' => 0, 'loan_deduction' => 0];
            $lines = [];
            foreach ($employee->payItems as $item) {
                $amount = PayrollMath::round2((float) $item->amount * $factor);
                $kind = $item->component->kind->value;
                $lines[] = ['label' => $item->component->name, 'kind' => $kind, 'amount' => $amount];
                match (true) {
                    $kind === 'RELIEF' => $in['relief'] += $amount,
                    $kind === 'DEDUCTION' => $in['post_tax_deductions'] += $amount,
                    (bool) $item->component->taxable => $in['taxable_allowances'] += $amount,
                    default => $in['non_taxable_allowances'] += $amount,
                };
            }
            foreach ($adjustments[$employee->id] ?? [] as $item) {
                $amount = (float) $item->amount;
                $kind = $item->kind->value;
                $lines[] = ['label' => $item->label, 'kind' => $kind, 'amount' => $amount];
                match (true) {
                    $kind === 'BONUS' => $in['bonus'] += $amount,
                    $kind === 'COMMISSION' => $in['commission'] += $amount,
                    $kind === 'DEDUCTION' => $in['post_tax_deductions'] += $amount,
                    (bool) $item->taxable => $in['taxable_allowances'] += $amount,
                    default => $in['non_taxable_allowances'] += $amount,
                };
            }
            foreach ($overtime[$employee->id] ?? [] as $item) {
                $in['overtime'] += PayrollMath::overtime($basic, $hoursPerMonth, (float) $item->hours, (float) $item->multiplier);
            }
            if ($in['overtime'] > 0) {
                $lines[] = ['label' => 'Overtime', 'kind' => 'OVERTIME', 'amount' => PayrollMath::round2($in['overtime'])];
            }
            $in['loan_deduction'] = $employee->loans->sum(fn ($loan) => min((float) $loan->balance, (float) $loan->monthly_deduction));

            $result = PayrollMath::calculate($in, $rates);
            unset($result['bonus_tax'], $result['employer_cost']);
            $slips[] = ['employee_id' => $employee->id, ...$result, 'lines' => $lines];
        }

        $totals = [
            'total_gross' => PayrollMath::round2(array_sum(array_column($slips, 'gross_pay'))),
            'total_net' => PayrollMath::round2(array_sum(array_column($slips, 'net_pay'))),
            'total_paye' => PayrollMath::round2(array_sum(array_column($slips, 'paye'))),
            'total_ssnit' => PayrollMath::round2(array_sum(array_column($slips, 'employee_ssnit')) + array_sum(array_column($slips, 'employer_ssnit'))),
        ];

        $run = DB::transaction(function () use ($year, $month, $userId, $notes, $totals, $slips) {
            $run = PayrollRun::lockForUpdate()->firstOrNew(compact('year', 'month'));
            if ($run->exists && $run->status === PayrollStatus::APPROVED) {
                throw ValidationException::withMessages(['month' => 'This month has already been approved.']);
            }
            $run->fill([...$totals, 'status' => PayrollStatus::PENDING_APPROVAL, 'processed_by' => $userId, 'approved_by' => null, 'notes' => $notes ?? $run->notes])->save();
            Payslip::where('payroll_run_id', $run->id)->delete();
            foreach ($slips as $slip) {
                Payslip::create(['payroll_run_id' => $run->id, ...$slip]);
            }

            return $run;
        });

        $this->notifier->notify($this->hrUserIds(), 'Payroll ready for approval', 'Payroll for '.self::period($year, $month).' is waiting for approval.', 'PAYROLL', '/payroll');
        Audit::log('CREATE', 'PayrollRun', $run->id, "Processed payroll {$year}-".str_pad((string) $month, 2, '0', STR_PAD_LEFT), userId: $userId);

        return $run;
    }

    /** Approves the run, takes this month's repayments off loan balances, and releases payslips to employees. */
    public function approve(PayrollRun $run, User $user, ?string $note = null): void
    {
        DB::transaction(function () use ($run, $user, $note) {
            $run = PayrollRun::lockForUpdate()->findOrFail($run->id);
            abort_unless($run->status === PayrollStatus::PENDING_APPROVAL, 422, 'Only a run waiting for approval can be approved.');

            foreach ($run->payslips()->where('loan_deduction', '>', 0)->get() as $slip) {
                $remaining = (float) $slip->loan_deduction;
                foreach (Loan::where('employee_id', $slip->employee_id)->where('status', 'ACTIVE')->orderBy('started_on')->lockForUpdate()->get() as $loan) {
                    if ($remaining <= 0) {
                        break;
                    }
                    $take = min($remaining, (float) $loan->balance);
                    $balance = PayrollMath::round2((float) $loan->balance - $take);
                    $loan->update(['balance' => $balance, 'status' => $balance <= 0 ? 'CLOSED' : 'ACTIVE']);
                    $remaining = PayrollMath::round2($remaining - $take);
                }
            }
            $run->update(['status' => PayrollStatus::APPROVED, 'approved_by' => $user->id, 'notes' => $note ?: $run->notes]);
        });

        $accounts = User::whereIn('employee_id', $run->payslips()->pluck('employee_id'))->pluck('id')->all();
        $this->notifier->notify($accounts, 'Payslip available', 'Your payslip for '.self::period($run->year, $run->month).' is ready.', 'PAYROLL', '/payroll');
        Audit::log('UPDATE', 'PayrollRun', $run->id, 'Approved payroll '.self::period($run->year, $run->month));
    }

    public function cancel(PayrollRun $run, ?string $note = null): void
    {
        abort_unless($run->status === PayrollStatus::PENDING_APPROVAL, 422, 'Only a run waiting for approval can be cancelled.');
        $run->update(['status' => PayrollStatus::CANCELLED, 'notes' => $note ?: $run->notes]);
        Audit::log('UPDATE', 'PayrollRun', $run->id, 'Cancelled payroll '.self::period($run->year, $run->month));
    }

    /** Recalculates the month after a bonus, deduction or loan, if its run is waiting for approval. */
    public function refresh(int $year, int $month, string $why): void
    {
        $run = PayrollRun::where(compact('year', 'month'))->first();
        if ($run?->status === PayrollStatus::PENDING_APPROVAL) {
            $this->process($year, $month, auth()->id(), $why);
        }
    }

    private function hrUserIds(): array
    {
        return User::where('is_active', true)->whereIn('role', [Role::SUPER_ADMIN, Role::HR_ADMIN, Role::HR_MANAGER])->pluck('id')->all();
    }
}
