<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statutory_configs', function (Blueprint $table) {
            $table->string('id', 30)->primary();
            $table->string('name');
            $table->string('kind');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->decimal('employee_rate', 8, 5)->nullable();
            $table->decimal('employer_rate', 8, 5)->nullable();
            $table->decimal('tier1_employer_rate', 8, 5)->nullable();
            $table->decimal('tier2_rate', 8, 5)->nullable();
            $table->json('bands')->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at');
            $table->index(['kind', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statutory_configs');
    }
};
