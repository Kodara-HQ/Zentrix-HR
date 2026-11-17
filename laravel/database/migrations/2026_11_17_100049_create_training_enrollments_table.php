<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_enrollments', function (Blueprint $table) {
            $table->string('id', 30)->primary();
            $table->string('program_id');
            $table->string('employee_id');
            $table->string('status', 40)->default('ENROLLED');
            $table->decimal('score', 5, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->boolean('attended')->default(false);
            $table->dateTime('created_at')->useCurrent();
            $table->unique(['program_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_enrollments');
    }
};
