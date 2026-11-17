<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Generated from the Prisma schema of the Node version; edit freely.
class DisciplinaryCase extends Model
{
    use HasUlids;

    protected $table = 'disciplinary_cases';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'incident_date' => 'date',
            'status' => \App\Enums\CaseStatus::class,
            'hearing_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function warnings(): HasMany
    {
        return $this->hasMany(Warning::class, 'case_id');
    }
}
