<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('separations', function (Blueprint $table) {
            $table->string('id', 30)->primary();
            $table->string('employee_id');
            $table->string('type', 40);
            $table->text('reason');
            $table->date('notice_date');
            $table->date('last_working_day');
            $table->string('status', 40)->default('PENDING');
            $table->text('interview_notes')->nullable();
            $table->text('final_pay_notes')->nullable();
            $table->text('decision_note')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('separations');
    }
};
