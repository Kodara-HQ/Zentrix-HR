<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_events', function (Blueprint $table) {
            $table->string('id', 30)->primary();
            $table->string('employee_id');
            $table->string('type', 40);
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('from_value')->nullable();
            $table->json('to_value')->nullable();
            $table->date('effective_date');
            $table->string('created_by_id')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->index(['employee_id', 'effective_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_events');
    }
};
