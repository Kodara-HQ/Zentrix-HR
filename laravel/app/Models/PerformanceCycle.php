<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Generated from the Prisma schema of the Node version; edit freely.
class PerformanceCycle extends Model
{
    use HasUlids;

    protected $table = 'performance_cycles';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'period_type' => \App\Enums\PeriodType::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => \App\Enums\CycleStatus::class,
        ];
    }

    public function objectives(): HasMany
    {
        return $this->hasMany(Objective::class, 'cycle_id');
    }

    public function appraisals(): HasMany
    {
        return $this->hasMany(Appraisal::class, 'cycle_id');
    }
}
