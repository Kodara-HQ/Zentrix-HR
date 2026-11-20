<?php

namespace App\Support;

use App\Models\Company;
use App\Models\User;

/**
 * The company whose data the current request or job works with.
 *
 * Signed-in staff always work in their own company. A system administrator works in no company
 * (and sees only the Companies pages) until they open one, which is remembered in the session.
 * Console commands, seeders and company setup choose a company explicitly with Tenant::run().
 */
class Tenant
{
    /** Session key holding the company a system administrator has opened. */
    public const SESSION_KEY = 'acting_company_id';

    /** Every table that holds one company's data (all of them except companies and framework tables). */
    public const TABLES = [
        'departments', 'job_titles', 'employment_types', 'work_locations', 'users', 'audit_logs', 'notifications',
        'notification_settings', 'document_categories', 'leave_types', 'shift_patterns', 'holidays', 'working_calendars',
        'payroll_components', 'statutory_configs', 'workflow_configs', 'rating_scales', 'employees', 'employee_educations',
        'employee_qualifications', 'employee_skills', 'employee_experiences', 'employee_bank_accounts', 'employee_events',
        'employee_pay_items', 'file_assets', 'biometric_devices', 'manpower_requisitions', 'vacancies', 'applicants',
        'interviews', 'job_offers', 'shift_assignments', 'attendance_records', 'attendance_corrections', 'overtime_requests',
        'leave_balances', 'leave_requests', 'loans', 'pay_adjustments', 'payroll_runs', 'payslips', 'performance_cycles',
        'objectives', 'appraisals', 'improvement_plans', 'training_providers', 'training_programs', 'training_enrollments',
        'training_needs', 'certifications', 'development_plans', 'grievances', 'disciplinary_cases', 'warnings',
        'welfare_requests', 'staff_feedbacks', 'surveys', 'survey_responses', 'recognitions', 'engagement_activities',
        'suggestions', 'contracts', 'hr_letters', 'separations', 'clearance_items', 'manpower_budgets', 'succession_plans',
    ];

    /** Matches nothing, for an account that somehow has no company. */
    private const NONE = '-';

    private static bool $forced = false;

    private static ?string $forcedId = null;

    /** The current company's id, or null when no company applies (system pages, guests, console). */
    public static function id(): ?string
    {
        if (self::$forced) {
            return self::$forcedId;
        }

        $guard = auth()->guard();
        if (! $guard->hasUser()) {
            return null;
        }

        /** @var User $user */
        $user = $guard->user();
        if ($user->isSystemAdmin()) {
            return app()->bound('session.store') && request()->hasSession() ? request()->session()->get(self::SESSION_KEY) : null;
        }

        return $user->company_id ?: self::NONE;
    }

    public static function company(): ?Company
    {
        $id = self::id();

        return $id ? Company::find($id) : null;
    }

    /** Company name for page titles, letters and PDFs. */
    public static function name(): string
    {
        $id = self::id();

        return ($id ? Company::whereKey($id)->value('name') : null) ?: 'Zentrix HR';
    }

    /** Runs $callback with $companyId as the current company (null: no company, so nothing is filtered). */
    public static function run(?string $companyId, callable $callback): mixed
    {
        [$forced, $forcedId] = [self::$forced, self::$forcedId];
        self::$forced = true;
        self::$forcedId = $companyId;
        try {
            return $callback();
        } finally {
            [self::$forced, self::$forcedId] = [$forced, $forcedId];
        }
    }

    /** Fixes the current company until forget() (used by tests and seeders). */
    public static function set(?string $companyId): void
    {
        self::$forced = true;
        self::$forcedId = $companyId;
    }

    public static function forget(): void
    {
        self::$forced = false;
        self::$forcedId = null;
    }

    public static function isTenantTable(string $table): bool
    {
        return in_array($table, self::TABLES, true);
    }
}
