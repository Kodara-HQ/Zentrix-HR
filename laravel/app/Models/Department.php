<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Generated from the Prisma schema of the Node version; edit freely.
class Department extends Model
{
    use BelongsToCompany, HasUlids;

    protected $table = 'departments';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [

        ];
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'department_id');
    }

    public function jobTitles(): HasMany
    {
        return $this->hasMany(JobTitle::class, 'department_id');
    }

    public function vacancies(): HasMany
    {
        return $this->hasMany(Vacancy::class, 'department_id');
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(ManpowerBudget::class, 'department_id');
    }

    public function objectives(): HasMany
    {
        return $this->hasMany(Objective::class, 'department_id');
    }
}
