<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Generated from the Prisma schema of the Node version; edit freely.
class ImprovementPlan extends Model
{
    use HasUlids;

    protected $table = 'improvement_plans';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'review_date' => 'date',
        ];
    }

    public function appraisal(): BelongsTo
    {
        return $this->belongsTo(Appraisal::class, 'appraisal_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
