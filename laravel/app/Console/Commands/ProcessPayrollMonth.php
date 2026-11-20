<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\PayrollRun;
use App\Services\PayrollService;
use App\Support\Tenant;
use Illuminate\Console\Command;

/**
 * Calculates this month's payroll if nobody has yet, and leaves it waiting for HR approval.
 * Scheduled daily in routes/console.php (replaces ensureCurrentMonthPayroll in the Node version,
 * which approved the month by itself).
 */
class ProcessPayrollMonth extends Command
{
    protected $signature = 'hr:payroll-month {--year=} {--month=}';

    protected $description = "Calculate a month's payroll (this month by default) if it has not been processed yet";

    public function handle(PayrollService $payroll): int
    {
        $year = (int) ($this->option('year') ?: now()->year);
        $month = (int) ($this->option('month') ?: now()->month);
        $period = PayrollService::period($year, $month);

        foreach (Company::where('is_active', true)->get() as $company) {
            Tenant::run($company->id, function () use ($payroll, $year, $month, $period, $company) {
                if ($run = PayrollRun::where(compact('year', 'month'))->first()) {
                    $this->line("{$company->name}: {$period} already exists ({$run->status->value}); nothing to do.");

                    return;
                }

                $run = $payroll->process($year, $month, null, 'Generated automatically for the month.');
                $this->info("{$company->name}: {$period} calculated for {$run->payslips()->count()} employees and is waiting for HR approval.");
            });
        }

        return self::SUCCESS;
    }
}
