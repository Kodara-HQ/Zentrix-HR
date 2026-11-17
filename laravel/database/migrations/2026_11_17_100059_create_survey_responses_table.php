<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_responses', function (Blueprint $table) {
            $table->string('id', 30)->primary();
            $table->string('survey_id');
            $table->string('employee_id')->nullable();
            $table->json('answers');
            $table->dateTime('created_at')->useCurrent();
            $table->unique(['survey_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_responses');
    }
};
