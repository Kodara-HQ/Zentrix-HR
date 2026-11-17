<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_cycles', function (Blueprint $table) {
            $table->string('id', 30)->primary();
            $table->string('name');
            $table->string('period_type', 40);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 40)->default('DRAFT');
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_cycles');
    }
};
