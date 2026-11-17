<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Generated from the Prisma schema of the Node version; edit freely.
class LeaveType extends Model
{
    use HasUlids;

    protected $table = 'leave_types';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'days_per_year' => 'decimal:1',
            'count_mode' => \App\Enums\LeaveCountMode::class,
            'affects_balance' => 'boolean',
            'paid' => 'boolean',
            'gender' => \App\Enums\Gender::class,
            'requires_reason' => 'boolean',
        ];
    }

    public function balances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class, 'leave_type_id');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'leave_type_id');
    }
}
