<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Generated from the Prisma schema of the Node version; edit freely.
class JobTitle extends Model
{
    use HasUlids;

    protected $table = 'job_titles';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [

        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'job_title_id');
    }

    public function vacancies(): HasMany
    {
        return $this->hasMany(Vacancy::class, 'job_title_id');
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(ManpowerBudget::class, 'job_title_id');
    }
}
