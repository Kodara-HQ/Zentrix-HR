<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('engagement_activities', function (Blueprint $table) {
            $table->string('id', 30)->primary();
            $table->string('title');
            $table->date('activity_date');
            $table->string('location')->nullable();
            $table->text('description')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engagement_activities');
    }
};
