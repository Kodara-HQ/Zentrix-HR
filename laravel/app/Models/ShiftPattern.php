<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Generated from the Prisma schema of the Node version; edit freely.
class ShiftPattern extends Model
{
    use HasUlids;

    protected $table = 'shift_patterns';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'grace_minutes' => 'integer',
            'work_days' => 'array',
            'is_night' => 'boolean',
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class, 'shift_id');
    }
}
