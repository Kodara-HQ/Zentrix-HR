<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->string('id', 30)->primary();
            $table->string('employee_no')->unique();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('gender', 40);
            $table->date('date_of_birth');
            $table->string('email')->unique();
            $table->string('phone');
            $table->string('alt_phone')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('region')->nullable();
            $table->string('emergency_name')->nullable();
            $table->string('emergency_phone')->nullable();
            $table->string('emergency_relation')->nullable();
            $table->string('next_of_kin_name')->nullable();
            $table->string('next_of_kin_phone')->nullable();
            $table->string('next_of_kin_relation')->nullable();
            $table->string('department_id');
            $table->string('job_title_id');
            $table->string('employment_type_id');
            $table->string('location_id');
            $table->string('manager_id')->nullable();
            $table->string('status', 40);
            $table->date('date_of_employment');
            $table->date('probation_end_date')->nullable();
            $table->decimal('basic_salary', 14, 2);
            $table->string('ssnit_number')->nullable();
            $table->string('tin_number')->nullable();
            $table->string('photo_file_id')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at');
            $table->index(['department_id', 'status']);
            $table->index(['location_id']);
            $table->index(['manager_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
