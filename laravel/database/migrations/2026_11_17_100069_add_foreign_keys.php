<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Foreign keys are added after every table exists because some tables point at each other.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_titles', function (Blueprint $table) {
            $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('employee_id')->references('id')->on('employees')->nullOnDelete();
        });
        Schema::table('notifications', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
        Schema::table('employees', function (Blueprint $table) {
            $table->foreign('department_id')->references('id')->on('departments')->restrictOnDelete();
        });
        Schema::table('employees', function (Blueprint $table) {
            $table->foreign('job_title_id')->references('id')->on('job_titles')->restrictOnDelete();
        });
        Schema::table('employees', function (Blueprint $table) {
            $table->foreign('employment_type_id')->references('id')->on('employment_types')->restrictOnDelete();
        });
        Schema::table('employees', function (Blueprint $table) {
            $table->foreign('location_id')->references('id')->on('work_locations')->restrictOnDelete();
        });
        Schema::table('employees', function (Blueprint $table) {
            $table->foreign('manager_id')->references('id')->on('employees')->nullOnDelete();
        });
        Schema::table('employee_educations', function (Blueprint $table) {
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
        });
        Schema::table('employee_qualifications', function (Blueprint $table) {
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
        });
        Schema::table('employee_skills', function (Blueprint $table) {
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
        });
        Schema::table('employee_experiences', function (Blueprint $table) {
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
        });
        Schema::table('employee_bank_accounts', function (Blueprint $table) {
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
        });
        Schema::table('employee_events', function (Blueprint $table) {
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
        });
        Schema::table('employee_pay_items', function (Blueprint $table) {
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
        });
        Schema::table('employee_pay_items', function (Blueprint $table) {
            $table->foreign('component_id')->references('id')->on('payroll_components')->restrictOnDelete();
        });
        Schema::table('vacancies', function (Blueprint $table) {
            $table->foreign('requisition_id')->references('id')->on('manpower_requisitions')->nullOnDelete();
        });
        Schema::table('vacancies', function (Blueprint $table) {
            $table->foreign('department_id')->references('id')->on('departments')->restrictOnDelete();
        });
        Schema::table('vacancies', function (Blueprint $table) {
            $table->foreign('job_title_id')->references('id')->on('job_titles')->restrictOnDelete();
        });
        Schema::table('vacancies', function (Blueprint $table) {
            $table->foreign('location_id')->references('id')->on('work_locations')->restrictOnDelete();
        });
        Schema::table('applicants', function (Blueprint $table) {
            $table->foreign('vacancy_id')->references('id')->on('vacancies')->cascadeOnDelete();
        });
        Schema::table('interviews', function (Blueprint $table) {
            $table->foreign('applicant_id')->references('id')->on('applicants')->cascadeOnDelete();
        });
        Schema::table('job_offers', function (Blueprint $table) {
            $table->foreign('applicant_id')->references('id')->on('applicants')->cascadeOnDelete();
        });
        Schema::table('shift_assignments', function (Blueprint $table) {
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
        });
        Schema::table('shift_assignments', function (Blueprint $table) {
            $table->foreign('shift_id')->references('id')->on('shift_patterns')->restrictOnDelete();
        });
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
        });
        Schema::table('attendance_corrections', function (Blueprint $table) {
            $table->foreign('attendance_id')->references('id')->on('attendance_records')->nullOnDelete();
        });
        Schema::table('attendance_corrections', function (Blueprint $table) {
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
        });
        Schema::table('overtime_requests', function (Blueprint $table) {
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
        });
        Schema::table('leave_balances', function (Blueprint $table) {
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
        });
        Schema::table('leave_balances', function (Blueprint $table) {
            $table->foreign('leave_type_id')->references('id')->on('leave_types')->restrictOnDelete();
        });
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
        });
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->foreign('leave_type_id')->references('id')->on('leave_types')->restrictOnDelete();
        });
        Schema::table('loans', function (Blueprint $table) {
            $table->foreign('employee_id')->references('id')->on('employees')->restrictOnDelete();
        });
        Schema::table('pay_adjustments', function (Blueprint $table) {
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
        });
        Schema::table('payslips', function (Blueprint $table) {
            $table->foreign('payroll_run_id')->references('id')->on('payroll_runs')->cascadeOnDelete();
        });
        Schema::table('payslips', function (Blueprint $table) {
            $table->foreign('employee_id')->references('id')->on('employees')->restrictOnDelete();
        });
        Schema::table('objectives', function (Blueprint $table) {
            $table->foreign('cycle_id')->references('id')->on('performance_cycles')->cascadeOnDelete();
        });
        Schema::table('objectives', function (Blueprint $table) {
            $table->foreign('employee_id')->references('id')->on('employees')->nullOnDelete();
        });
        Schema::table('objectives', function (Blueprint $table) {
            $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
        });
        Schema::table('appraisals', function (Blueprint $table) {
            $table->foreign('cycle_id')->references('id')->on('performance_cycles')->cascadeOnDelete();
        });
        Schema::table('appraisals', function (Blueprint $table) {
            $table->foreign('employee_id')->references('id')->on('employees')->restrictOnDelete();
        });
        Schema::table('improvement_plans', function (Blueprint $table) {
            $table->foreign('appraisal_id')->references('id')->on('appraisals')->nullOnDelete();
        });
        Schema::table('improvement_plans', function (Blueprint $table) {
            $table->foreign('employee_id')->references('id')->on('employees')->restrictOnDelete();
        });
        Schema::table('training_programs', function (Blueprint $table) {
            $table->foreign('provider_id')->references('id')->on('training_providers')->nullOnDelete();
        });
        Schema::table('training_enrollments', function (Blueprint $table) {
            $table->foreign('program_id')->references('id')->on('training_programs')->cascadeOnDelete();
        });
        Schema::table('training_enrollments', function (Blueprint $table) {
            $table->foreign('employee_id')->references('id')->on('employees')->restrictOnDelete();
        });
        Schema::table('certifications', function (Blueprint $table) {
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
        });
        Schema::table('development_plans', function (Blueprint $table) {
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
        });
        Schema::table('grievances', function (Blueprint $table) {
            $table->foreign('employee_id')->references('id')->on('employees')->restrictOnDelete();
        });
        Schema::table('disciplinary_cases', function (Blueprint $table) {
            $table->foreign('employee_id')->references('id')->on('employees')->restrictOnDelete();
        });
        Schema::table('warnings', function (Blueprint $table) {
            $table->foreign('case_id')->references('id')->on('disciplinary_cases')->nullOnDelete();
        });
        Schema::table('warnings', function (Blueprint $table) {
            $table->foreign('employee_id')->references('id')->on('employees')->restrictOnDelete();
        });
        Schema::table('welfare_requests', function (Blueprint $table) {
            $table->foreign('employee_id')->references('id')->on('employees')->restrictOnDelete();
        });
        Schema::table('survey_responses', function (Blueprint $table) {
            $table->foreign('survey_id')->references('id')->on('surveys')->cascadeOnDelete();
        });
        Schema::table('recognitions', function (Blueprint $table) {
            $table->foreign('employee_id')->references('id')->on('employees')->restrictOnDelete();
        });
        Schema::table('contracts', function (Blueprint $table) {
            $table->foreign('employee_id')->references('id')->on('employees')->restrictOnDelete();
        });
        Schema::table('hr_letters', function (Blueprint $table) {
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
        });
        Schema::table('separations', function (Blueprint $table) {
            $table->foreign('employee_id')->references('id')->on('employees')->restrictOnDelete();
        });
        Schema::table('clearance_items', function (Blueprint $table) {
            $table->foreign('separation_id')->references('id')->on('separations')->cascadeOnDelete();
        });
        Schema::table('manpower_budgets', function (Blueprint $table) {
            $table->foreign('department_id')->references('id')->on('departments')->restrictOnDelete();
        });
        Schema::table('manpower_budgets', function (Blueprint $table) {
            $table->foreign('job_title_id')->references('id')->on('job_titles')->nullOnDelete();
        });
        Schema::table('succession_plans', function (Blueprint $table) {
            $table->foreign('incumbent_id')->references('id')->on('employees')->nullOnDelete();
        });
        Schema::table('succession_plans', function (Blueprint $table) {
            $table->foreign('successor_id')->references('id')->on('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
    }
};
