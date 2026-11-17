<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surveys', function (Blueprint $table) {
            $table->string('id', 30)->primary();
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('questions');
            $table->date('opens_on')->nullable();
            $table->date('closes_on')->nullable();
            $table->boolean('is_open')->default(true);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surveys');
    }
};
