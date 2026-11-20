<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Generated from the Prisma schema of the Node version; edit freely.
class User extends Authenticatable
{
    use BelongsToCompany, HasUlids, Notifiable;

    protected $table = 'users';

    protected $guarded = ['id'];

    protected $hidden = ['password_hash', 'remember_token'];

    /** Set while a system administrator works inside a company; the stored role stays SYSTEM_ADMIN. */
    public ?string $actingInCompany = null;

    protected static function booted(): void
    {
        // Never store the stand-in role or company of a system administrator who opened a company.
        static::saving(function (User $user) {
            if ($user->actingInCompany) {
                $user->role = \App\Enums\Role::SYSTEM_ADMIN;
                $user->company_id = null;
            }
        });
        static::saved(function (User $user) {
            if ($user->actingInCompany) {
                $user->actAsCompanyAdmin($user->actingInCompany);
            }
        });
    }

    public function isSystemAdmin(): bool
    {
        return $this->actingInCompany !== null || $this->role === \App\Enums\Role::SYSTEM_ADMIN;
    }

    /** For this request, a system administrator works in the company as its super administrator. */
    public function actAsCompanyAdmin(string $companyId): void
    {
        $this->actingInCompany = $companyId;
        $this->role = \App\Enums\Role::SUPER_ADMIN;
        $this->company_id = $companyId;
    }

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function getRememberTokenName(): ?string
    {
        return null;
    }

    protected function casts(): array
    {
        return [
            'role' => \App\Enums\Role::class,
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'user_id');
    }
}
