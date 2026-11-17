<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('improvement_plans', function (Blueprint $table) {
            $table->string('id', 30)->primary();
            $table->string('appraisal_id')->nullable();
            $table->string('employee_id');
            $table->text('goals');
            $table->text('support')->nullable();
            $table->date('review_date')->nullable();
            $table->string('status')->default('OPEN');
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('improvement_plans');
    }
};
