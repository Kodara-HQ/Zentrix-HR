<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Generated from the Prisma schema of the Node version; edit freely.
class PayrollRun extends Model
{
    use BelongsToCompany, HasUlids;

    protected $table = 'payroll_runs';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'status' => \App\Enums\PayrollStatus::class,
            'total_gross' => 'decimal:2',
            'total_net' => 'decimal:2',
            'total_paye' => 'decimal:2',
            'total_ssnit' => 'decimal:2',
        ];
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class, 'payroll_run_id');
    }
}
