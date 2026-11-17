<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interviews', function (Blueprint $table) {
            $table->string('id', 30)->primary();
            $table->string('applicant_id');
            $table->dateTime('scheduled_at');
            $table->string('interviewer_id')->nullable();
            $table->string('mode');
            $table->string('location')->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->text('comments')->nullable();
            $table->text('recommendation')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interviews');
    }
};
