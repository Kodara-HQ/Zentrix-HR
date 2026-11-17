<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payslips', function (Blueprint $table) {
            $table->string('id', 30)->primary();
            $table->string('payroll_run_id');
            $table->string('employee_id');
            $table->decimal('basic_salary', 14, 2);
            $table->decimal('allowances', 14, 2);
            $table->decimal('overtime_pay', 14, 2);
            $table->decimal('bonus', 14, 2);
            $table->decimal('commission', 14, 2);
            $table->decimal('gross_pay', 14, 2);
            $table->decimal('employee_ssnit', 14, 2);
            $table->decimal('employer_ssnit', 14, 2);
            $table->decimal('tier1', 14, 2);
            $table->decimal('tier2', 14, 2);
            $table->decimal('reliefs', 14, 2);
            $table->decimal('chargeable_income', 14, 2);
            $table->decimal('paye', 14, 2);
            $table->decimal('other_deductions', 14, 2);
            $table->decimal('loan_deduction', 14, 2);
            $table->decimal('net_pay', 14, 2);
            $table->json('lines');
            $table->dateTime('created_at')->useCurrent();
            $table->unique(['payroll_run_id', 'employee_id']);
            $table->index(['employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslips');
    }
};
