<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One organisation using the system. Every other record belongs to exactly one company; only system
 * administrators create and edit companies (see SystemController).
 */
class Company extends Model
{
    use HasUlids;

    protected $table = 'companies';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'next_employee_no' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'company_id')->withoutGlobalScope('company');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'company_id')->withoutGlobalScope('company');
    }
}
