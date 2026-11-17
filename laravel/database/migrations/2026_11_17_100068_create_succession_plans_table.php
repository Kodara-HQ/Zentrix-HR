<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('succession_plans', function (Blueprint $table) {
            $table->string('id', 30)->primary();
            $table->string('position_title');
            $table->string('department_id')->nullable();
            $table->string('incumbent_id')->nullable();
            $table->string('successor_id')->nullable();
            $table->string('readiness');
            $table->boolean('critical')->default(false);
            $table->text('notes')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('succession_plans');
    }
};
