<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Company;
use App\Models\DocumentCategory;
use App\Models\EmploymentType;
use App\Models\LeaveType;
use App\Models\NotificationSetting;
use App\Models\User;
use App\Models\WorkflowConfig;
use App\Support\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Sets up a company: the company record, the settings every company needs (leave types, pay rules,
 * holidays, workflows, ...) and its first super administrator. Used by the system administrator's
 * Companies page and by the seeders.
 */
class CompanySetup
{
    /**
     * @param  array  $company  name, legal_name, email, phone, city, country, currency, ...
     * @param  array|null  $admin  first_name, last_name, username, email, password
     */
    public function create(array $company, ?array $admin = null): Company
    {
        return DB::transaction(function () use ($company, $admin) {
            $record = Company::create($company + ['country' => 'Ghana', 'currency' => 'GHS', 'next_employee_no' => 1, 'is_active' => true]);
            Tenant::run($record->id, function () use ($admin) {
                $this->addReferenceData();
                if ($admin) {
                    User::create([
                        'first_name' => $admin['first_name'],
                        'last_name' => $admin['last_name'],
                        'username' => $admin['username'],
                        'email' => $admin['email'] ?? null,
                        'password_hash' => Hash::make($admin['password']),
                        'role' => Role::SUPER_ADMIN,
                        'is_active' => true,
                        'must_change_password' => true,
                    ]);
                }
            });

            return $record;
        });
    }

    /** Settings every company needs, for the current company. Safe to run more than once. Ported from server/src/seed.ts. */
    public function addReferenceData(): void
    {
        app(\App\Services\PayrollService::class)->ensureSetup(); // PAYE bands, SSNIT rates and standard pay components

        if (! \App\Models\WorkingCalendar::exists()) {
            \App\Models\WorkingCalendar::create([
                'name' => 'Head office calendar', 'work_days' => [1, 2, 3, 4, 5], 'start_time' => '08:00', 'end_time' => '17:00',
                'hours_per_day' => 8, 'hours_per_month' => 176, 'grace_minutes' => 15, 'overtime_weekday' => 1.5, 'overtime_weekend' => 2, 'is_active' => true,
            ]);
        }

        // Ghana public holidays that fall on the same date every year. Movable ones (Eid, Easter, Farmers' Day) are added each year in settings.
        foreach ([
            ["New Year's Day", '01-01'], ['Constitution Day', '01-07'], ['Independence Day', '03-06'], ['May Day', '05-01'],
            ["Founders' Day", '08-04'], ['Kwame Nkrumah Memorial Day', '09-21'], ['Christmas Day', '12-25'], ['Boxing Day', '12-26'],
        ] as [$name, $monthDay]) {
            \App\Models\Holiday::firstOrCreate(['name' => $name, 'date' => "2026-{$monthDay}"], ['recurring' => true]);
        }

        foreach ([['Full-time', 'Open or fixed-term full-time employment'], ['Contract', 'Fixed-term contract'], ['Casual', 'Casual engagement']] as [$name, $description]) {
            EmploymentType::firstOrCreate(['name' => $name], ['description' => $description]);
        }

        foreach ([
            ['Annual leave', 'ANNUAL', 25, 'WORKING_DAYS', true, true, null, false, '#0f766e'],
            ['Sick leave', 'SICK', 5, 'WORKING_DAYS', true, true, null, true, '#b45309'],
            ['Maternity leave', 'MATERNITY', 84, 'CALENDAR_DAYS', true, true, 'FEMALE', true, '#9d174d'],
            ['Paternity leave', 'PATERNITY', 10, 'WORKING_DAYS', true, true, 'MALE', true, '#1d4e89'],
            ['Study leave', 'STUDY', 5, 'WORKING_DAYS', true, true, null, true, '#0f766e'],
        ] as [$name, $code, $days, $mode, $affects, $paid, $gender, $reason, $color]) {
            LeaveType::firstOrCreate(['code' => $code], [
                'name' => $name, 'days_per_year' => $days, 'count_mode' => $mode, 'affects_balance' => $affects,
                'paid' => $paid, 'gender' => $gender, 'requires_reason' => $reason, 'color' => $color,
            ]);
        }

        foreach ([
            ['Employment contract', false], ['Appointment letter', false], ['Promotion letter', false], ['Transfer letter', false],
            ['Warning letter', true], ['Confirmation letter', false], ['Termination letter', true], ['Identification', true],
            ['Academic certificate', false], ['Professional licence', false], ['Curriculum vitae', false],
        ] as [$name, $confidential]) {
            DocumentCategory::firstOrCreate(['name' => $name], ['confidential' => $confidential]);
        }

        foreach ([
            ['LEAVE', 'Leave approval', ['MANAGER', 'HR']],
            ['PAYROLL', 'Payroll approval', ['PAYROLL_OFFICER', 'HR_MANAGER']],
            ['REQUISITION', 'Manpower requisition', ['HR_MANAGER']],
            ['SEPARATION', 'Separation', ['HR_MANAGER']],
            ['OVERTIME', 'Overtime', ['MANAGER']],
            ['ATTENDANCE', 'Attendance correction', ['MANAGER']],
        ] as [$key, $label, $steps]) {
            WorkflowConfig::firstOrCreate(['key' => $key], ['label' => $label, 'steps' => $steps]);
        }

        foreach ([
            'LEAVE' => 'Leave decisions', 'ATTENDANCE' => 'Attendance corrections', 'RECRUITMENT' => 'Recruitment updates',
            'PAYROLL' => 'Payroll processing', 'APPRAISAL' => 'Appraisal deadlines', 'TRAINING' => 'Training schedules',
            'BIRTHDAY' => 'Birthdays', 'CONTRACT_EXPIRY' => 'Contract expiration', 'PROBATION' => 'Probation completion',
            'CERTIFICATION' => 'Certification expiration', 'HEARING' => 'Disciplinary hearings', 'GRIEVANCE' => 'Grievances',
            'WELFARE' => 'Welfare requests', 'RECOGNITION' => 'Recognition', 'SEPARATION' => 'Exits',
            'DISCIPLINE' => 'Warnings', 'EMPLOYEE_STATUS' => 'Employment status',
        ] as $key => $label) {
            NotificationSetting::firstOrCreate(['event_key' => $key], ['label' => $label, 'in_app' => true, 'email' => false]);
        }
    }
}
