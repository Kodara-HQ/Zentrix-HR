<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\DocumentCategory;
use App\Models\EmploymentType;
use App\Models\LeaveType;
use App\Models\NotificationSetting;
use App\Models\WorkflowConfig;
use Illuminate\Database\Seeder;

/** Settings every installation needs. Safe to run more than once. Ported from server/src/seed.ts. */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        if (! Company::exists()) {
            Company::create(['name' => 'Zentrix HR', 'legal_name' => 'Zentrix HR', 'city' => 'Accra', 'country' => 'Ghana', 'currency' => 'GHS', 'next_employee_no' => 1]);
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
