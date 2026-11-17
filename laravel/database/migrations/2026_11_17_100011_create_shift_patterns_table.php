<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_patterns', function (Blueprint $table) {
            $table->string('id', 30)->primary();
            $table->string('name')->unique();
            $table->string('start_time');
            $table->string('end_time');
            $table->integer('grace_minutes')->default(15);
            $table->json('work_days');
            $table->boolean('is_night')->default(false);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_patterns');
    }
};
