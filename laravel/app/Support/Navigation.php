<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\User;

/** Sidebar entries, ported from client/src/components/Layout.tsx. */
class Navigation
{
    private const ITEMS = [
        ['path' => '/', 'label' => 'Dashboard', 'icon' => 'layout-dashboard', 'roles' => ['HR_ADMIN', 'HR_MANAGER', 'DEPARTMENT_MANAGER', 'SUPERVISOR', 'PAYROLL_OFFICER', 'RECRUITMENT_OFFICER']],
        ['path' => '/employees', 'label' => 'Employees', 'icon' => 'users', 'roles' => ['HR_ADMIN', 'HR_MANAGER', 'DEPARTMENT_MANAGER', 'SUPERVISOR', 'PAYROLL_OFFICER']],
        ['path' => '/recruitment', 'label' => 'Recruitment', 'icon' => 'briefcase', 'roles' => ['HR_ADMIN', 'HR_MANAGER', 'RECRUITMENT_OFFICER', 'DEPARTMENT_MANAGER']],
        ['path' => '/attendance', 'label' => 'Attendance', 'icon' => 'calendar-check', 'roles' => ['HR_ADMIN', 'HR_MANAGER', 'DEPARTMENT_MANAGER', 'SUPERVISOR']],
        ['path' => '/leave', 'label' => 'Leave Management', 'icon' => 'calendar-days', 'roles' => ['HR_ADMIN', 'HR_MANAGER', 'DEPARTMENT_MANAGER', 'SUPERVISOR']],
        ['path' => '/payroll', 'label' => 'Payroll', 'icon' => 'wallet', 'roles' => ['HR_ADMIN', 'HR_MANAGER', 'PAYROLL_OFFICER']],
        ['path' => '/performance', 'label' => 'Performance', 'icon' => 'chart-column', 'roles' => ['HR_ADMIN', 'HR_MANAGER', 'DEPARTMENT_MANAGER', 'SUPERVISOR']],
        ['path' => '/training', 'label' => 'Training', 'icon' => 'graduation-cap', 'roles' => ['HR_ADMIN', 'HR_MANAGER', 'DEPARTMENT_MANAGER', 'SUPERVISOR']],
        ['path' => '/relations', 'label' => 'Employee Relations', 'icon' => 'scale', 'roles' => ['HR_ADMIN', 'HR_MANAGER']],
        ['path' => '/welfare', 'label' => 'Employee Welfare', 'icon' => 'heart-handshake', 'roles' => ['HR_ADMIN', 'HR_MANAGER', 'DEPARTMENT_MANAGER']],
        ['path' => '/contracts', 'label' => 'Contracts & Documents', 'icon' => 'file-text', 'roles' => ['HR_ADMIN', 'HR_MANAGER', 'DEPARTMENT_MANAGER']],
        ['path' => '/workforce', 'label' => 'Workforce Planning', 'icon' => 'network', 'roles' => ['HR_ADMIN', 'HR_MANAGER', 'DEPARTMENT_MANAGER']],
        ['path' => '/exit', 'label' => 'Exit Management', 'icon' => 'door-open', 'roles' => ['HR_ADMIN', 'HR_MANAGER', 'DEPARTMENT_MANAGER']],
        ['path' => '/reports', 'label' => 'Reports & Analytics', 'icon' => 'chart-column', 'roles' => ['HR_ADMIN', 'HR_MANAGER', 'DEPARTMENT_MANAGER', 'PAYROLL_OFFICER', 'SUPERVISOR']],
        ['path' => '/notifications', 'label' => 'Notifications', 'icon' => 'bell', 'roles' => ['HR_ADMIN', 'HR_MANAGER', 'DEPARTMENT_MANAGER', 'SUPERVISOR', 'PAYROLL_OFFICER', 'RECRUITMENT_OFFICER']],
        ['path' => '/users', 'label' => 'User management', 'icon' => 'user-round', 'roles' => ['SUPER_ADMIN']],
        ['path' => '/settings', 'label' => 'System Settings', 'icon' => 'settings', 'roles' => ['HR_ADMIN', 'HR_MANAGER']],
    ];

    /** Every module page and the roles that may open it (used for routes). */
    public static function modules(): array
    {
        return array_values(array_filter(self::ITEMS, fn ($item) => $item['path'] !== '/'));
    }

    public static function for(User $user): array
    {
        if ($user->role === Role::EMPLOYEE) {
            return array_values(array_filter([
                ['path' => '/', 'label' => 'Home', 'icon' => 'layout-dashboard'],
                $user->employee_id ? ['path' => "/employees/{$user->employee_id}", 'label' => 'My profile', 'icon' => 'user-round'] : null,
                ['path' => '/attendance', 'label' => 'My attendance', 'icon' => 'calendar-check'],
                ['path' => '/leave', 'label' => 'My leave', 'icon' => 'calendar-days'],
                ['path' => '/payroll', 'label' => 'My payslips', 'icon' => 'wallet'],
                ['path' => '/performance', 'label' => 'My reviews', 'icon' => 'chart-column'],
                ['path' => '/training', 'label' => 'My training', 'icon' => 'graduation-cap'],
                ['path' => '/welfare', 'label' => 'Welfare', 'icon' => 'heart-handshake'],
                ['path' => '/notifications', 'label' => 'Notifications', 'icon' => 'bell'],
            ]));
        }

        return array_values(array_filter(self::ITEMS, fn ($item) => Access::hasRole($user, $item['roles'])));
    }

    public static function isActive(string $path): bool
    {
        $current = '/'.trim(request()->path(), '/');

        return $path === '/' ? $current === '/' : ($current === $path || str_starts_with($current, $path.'/'));
    }
}
