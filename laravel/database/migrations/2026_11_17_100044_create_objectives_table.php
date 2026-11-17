<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('objectives', function (Blueprint $table) {
            $table->string('id', 30)->primary();
            $table->string('cycle_id');
            $table->string('employee_id')->nullable();
            $table->string('department_id')->nullable();
            $table->string('level', 40);
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('kpi');
            $table->text('target');
            $table->decimal('weight', 5, 2);
            $table->text('actual')->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('objectives');
    }
};
