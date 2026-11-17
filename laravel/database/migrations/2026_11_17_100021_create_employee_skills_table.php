<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_skills', function (Blueprint $table) {
            $table->string('id', 30)->primary();
            $table->string('employee_id');
            $table->string('name');
            $table->string('level');
            $table->decimal('years', 4, 1)->nullable();
            $table->unique(['employee_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_skills');
    }
};
