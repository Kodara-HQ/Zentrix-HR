<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->string('id', 30)->primary();
            $table->string('employee_id');
            $table->string('type');
            $table->decimal('principal', 14, 2);
            $table->decimal('balance', 14, 2);
            $table->decimal('monthly_deduction', 14, 2);
            $table->string('status', 40)->default('ACTIVE');
            $table->date('started_on');
            $table->text('notes')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
