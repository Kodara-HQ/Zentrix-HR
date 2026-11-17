<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('working_calendars', function (Blueprint $table) {
            $table->string('id', 30)->primary();
            $table->string('name');
            $table->json('work_days');
            $table->string('start_time');
            $table->string('end_time');
            $table->decimal('hours_per_day', 4, 2);
            $table->decimal('hours_per_month', 6, 2);
            $table->integer('grace_minutes')->default(15);
            $table->decimal('overtime_weekday', 4, 2)->default(1.5);
            $table->decimal('overtime_weekend', 4, 2)->default(2.0);
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->integer('radius_meters')->default(200);
            $table->boolean('is_active')->default(true);
            $table->dateTime('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('working_calendars');
    }
};
