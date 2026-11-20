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

    /** Colours as the form shows them, with the standard ones when none are set. */
    public function themeColours(): array
    {
        return [
            $this->theme_primary ?: \App\Support\Theme::DEFAULT_PRIMARY,
            $this->theme_accent ?: \App\Support\Theme::DEFAULT_ACCENT,
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
