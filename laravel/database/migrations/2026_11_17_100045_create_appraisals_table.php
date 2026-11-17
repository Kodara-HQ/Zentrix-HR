<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appraisals', function (Blueprint $table) {
            $table->string('id', 30)->primary();
            $table->string('cycle_id');
            $table->string('employee_id');
            $table->string('reviewer_id')->nullable();
            $table->decimal('self_rating', 4, 2)->nullable();
            $table->text('self_comments')->nullable();
            $table->decimal('supervisor_rating', 4, 2)->nullable();
            $table->text('supervisor_comments')->nullable();
            $table->decimal('final_rating', 4, 2)->nullable();
            $table->text('recommendation')->nullable();
            $table->string('status', 40)->default('DRAFT');
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at');
            $table->unique(['cycle_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appraisals');
    }
};
