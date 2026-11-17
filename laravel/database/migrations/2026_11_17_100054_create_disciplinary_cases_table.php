<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disciplinary_cases', function (Blueprint $table) {
            $table->string('id', 30)->primary();
            $table->string('case_no')->unique();
            $table->string('employee_id');
            $table->date('incident_date');
            $table->text('description');
            $table->string('status', 40)->default('OPEN');
            $table->dateTime('hearing_at')->nullable();
            $table->text('outcome')->nullable();
            $table->text('confidential_notes')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disciplinary_cases');
    }
};
