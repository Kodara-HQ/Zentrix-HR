<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applicants', function (Blueprint $table) {
            $table->string('id', 30)->primary();
            $table->string('vacancy_id');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('phone');
            $table->string('source')->nullable();
            $table->string('status', 40)->default('APPLIED');
            $table->decimal('screening_score', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('employee_id')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at');
            $table->index(['vacancy_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicants');
    }
};
