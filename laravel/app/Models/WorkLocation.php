<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Generated from the Prisma schema of the Node version; edit freely.
class WorkLocation extends Model
{
    use HasUlids;

    protected $table = 'work_locations';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [

        ];
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'location_id');
    }

    public function vacancies(): HasMany
    {
        return $this->hasMany(Vacancy::class, 'location_id');
    }
}
