<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manpower_budgets', function (Blueprint $table) {
            $table->string('id', 30)->primary();
            $table->integer('year');
            $table->string('department_id');
            $table->string('job_title_id')->nullable();
            $table->integer('approved_headcount');
            $table->decimal('estimated_cost', 16, 2);
            $table->text('notes')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at');
            $table->unique(['year', 'department_id', 'job_title_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manpower_budgets');
    }
};
