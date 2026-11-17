<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->string('id', 30)->primary();
            $table->string('attendance_id')->nullable();
            $table->string('employee_id');
            $table->date('date');
            $table->text('reason');
            $table->dateTime('requested_clock_in')->nullable();
            $table->dateTime('requested_clock_out')->nullable();
            $table->string('status', 40)->default('PENDING_MANAGER');
            $table->text('decision_note')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_corrections');
    }
};
