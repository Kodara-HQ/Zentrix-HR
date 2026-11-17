<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Generated from the Prisma schema of the Node version; edit freely.
class PayAdjustment extends Model
{
    use HasUlids;

    protected $table = 'pay_adjustments';

    protected $guarded = ['id'];

    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'kind' => \App\Enums\AdjustmentKind::class,
            'amount' => 'decimal:2',
            'taxable' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
