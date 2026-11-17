<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->string('id', 30)->primary();
            $table->string('name')->unique();
            $table->string('code')->unique();
            $table->decimal('days_per_year', 6, 1);
            $table->string('count_mode', 40)->default('WORKING_DAYS');
            $table->boolean('affects_balance')->default(true);
            $table->boolean('paid')->default(true);
            $table->string('gender', 40)->nullable();
            $table->boolean('requires_reason')->default(false);
            $table->string('color')->default('#0f766e');
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_types');
    }
};
