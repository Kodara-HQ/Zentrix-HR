<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Generated from the Prisma schema of the Node version; edit freely.
class Vacancy extends Model
{
    use HasUlids;

    protected $table = 'vacancies';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'openings' => 'integer',
            'filled' => 'integer',
            'status' => \App\Enums\VacancyStatus::class,
            'salary_min' => 'decimal:2',
            'salary_max' => 'decimal:2',
            'advertising_cost' => 'decimal:2',
            'agency_cost' => 'decimal:2',
            'opened_on' => 'date',
            'closed_on' => 'date',
        ];
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(ManpowerRequisition::class, 'requisition_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function jobTitle(): BelongsTo
    {
        return $this->belongsTo(JobTitle::class, 'job_title_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class, 'location_id');
    }

    public function applicants(): HasMany
    {
        return $this->hasMany(Applicant::class, 'vacancy_id');
    }
}
