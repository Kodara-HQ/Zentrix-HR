<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Generated from the Prisma schema of the Node version; edit freely.
class Appraisal extends Model
{
    use HasUlids;

    protected $table = 'appraisals';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'self_rating' => 'decimal:2',
            'supervisor_rating' => 'decimal:2',
            'final_rating' => 'decimal:2',
            'status' => \App\Enums\AppraisalStatus::class,
        ];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(PerformanceCycle::class, 'cycle_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function plans(): HasMany
    {
        return $this->hasMany(ImprovementPlan::class, 'appraisal_id');
    }
}
