<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who may see and do what. Ported from server/src/lib/access.ts in the Node version.
 */
class Access
{
    public const HR = [Role::SUPER_ADMIN, Role::HR_ADMIN, Role::HR_MANAGER];

    public const COMPANY_WIDE = [Role::SUPER_ADMIN, Role::HR_ADMIN, Role::HR_MANAGER, Role::PAYROLL_OFFICER];

    public const SENSITIVE = ['basic_salary', 'ssnit_number', 'tin_number', 'bankAccounts', 'payItems', 'loans', 'payslips'];

    /** Super administrators pass every role check. */
    public static function hasRole(?User $user, array $roles): bool
    {
        if (! $user) {
            return false;
        }

        return $user->role === Role::SUPER_ADMIN || in_array($user->role, self::roles($roles), true);
    }

    public static function isHr(User $user): bool
    {
        return in_array($user->role, self::HR, true);
    }

    public static function canSeeCompensation(User $user, ?string $employeeId = null): bool
    {
        if (in_array($user->role, self::COMPANY_WIDE, true)) {
            return true;
        }

        return $employeeId !== null && $user->employee_id === $employeeId;
    }

    public static function canSeeDiscipline(User $user): bool
    {
        return self::isHr($user);
    }

    /** Limits an employee query to the people this user may see. */
    public static function scopeEmployees(Builder $query, User $user): Builder
    {
        if (in_array($user->role, self::COMPANY_WIDE, true)) {
            return $query;
        }
        if ($user->role === Role::DEPARTMENT_MANAGER && $user->department_id) {
            return $query->where('department_id', $user->department_id);
        }
        if ($user->role === Role::SUPERVISOR && $user->employee_id) {
            return $query->where(fn (Builder $q) => $q->where('manager_id', $user->employee_id)->orWhere('id', $user->employee_id));
        }
        if ($user->role === Role::EMPLOYEE && $user->employee_id) {
            return $query->where('id', $user->employee_id);
        }

        return $query->whereRaw('1 = 0');
    }

    public static function canSeeEmployee(User $user, Employee $employee): bool
    {
        if (in_array($user->role, self::COMPANY_WIDE, true)) {
            return true;
        }

        return match ($user->role) {
            Role::DEPARTMENT_MANAGER => $user->department_id === $employee->department_id,
            Role::SUPERVISOR => $employee->manager_id === $user->employee_id || $employee->id === $user->employee_id,
            Role::EMPLOYEE => $employee->id === $user->employee_id,
            default => false,
        };
    }

    public static function assertEmployeeVisible(User $user, Employee $employee): void
    {
        abort_unless(self::canSeeEmployee($user, $employee), 403, 'You do not have access to this employee.');
    }

    public static function canApproveAsManager(User $user, Employee $employee): bool
    {
        if ($user->employee_id && $user->employee_id === $employee->id) {
            return false;
        }
        if (self::isHr($user)) {
            return true;
        }
        if ($user->role === Role::DEPARTMENT_MANAGER && $user->department_id === $employee->department_id) {
            return true;
        }

        return $user->role === Role::SUPERVISOR && $user->employee_id && $employee->manager_id === $user->employee_id;
    }

    /** Pages an employee (ESS) account may open. */
    public static function employeeMayOpen(string $path, ?string $employeeId): bool
    {
        $path = '/'.trim($path, '/');
        if (in_array($path, ['/', '/attendance', '/leave', '/payroll', '/notifications', '/settings'], true)) {
            return true;
        }

        return $employeeId !== null && $path === "/employees/{$employeeId}";
    }

    /** @param  array<Role|string>  $roles */
    private static function roles(array $roles): array
    {
        return array_map(fn ($role) => $role instanceof Role ? $role : Role::from($role), $roles);
    }
}
