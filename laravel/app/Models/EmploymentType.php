<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Generated from the Prisma schema of the Node version; edit freely.
class EmploymentType extends Model
{
    use BelongsToCompany, HasUlids;

    protected $table = 'employment_types';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [

        ];
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'employment_type_id');
    }
}
