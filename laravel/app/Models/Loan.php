<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Generated from the Prisma schema of the Node version; edit freely.
class Loan extends Model
{
    use BelongsToCompany, HasUlids;

    protected $table = 'loans';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'principal' => 'decimal:2',
            'balance' => 'decimal:2',
            'monthly_deduction' => 'decimal:2',
            'status' => \App\Enums\LoanStatus::class,
            'started_on' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
