<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Generated from the Prisma schema of the Node version; edit freely.
class Payslip extends Model
{
    use BelongsToCompany, HasUlids;

    protected $table = 'payslips';

    protected $guarded = ['id'];

    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'basic_salary' => 'decimal:2',
            'allowances' => 'decimal:2',
            'overtime_pay' => 'decimal:2',
            'bonus' => 'decimal:2',
            'commission' => 'decimal:2',
            'gross_pay' => 'decimal:2',
            'employee_ssnit' => 'decimal:2',
            'employer_ssnit' => 'decimal:2',
            'tier1' => 'decimal:2',
            'tier2' => 'decimal:2',
            'reliefs' => 'decimal:2',
            'chargeable_income' => 'decimal:2',
            'paye' => 'decimal:2',
            'other_deductions' => 'decimal:2',
            'loan_deduction' => 'decimal:2',
            'net_pay' => 'decimal:2',
            'lines' => 'array',
        ];
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
