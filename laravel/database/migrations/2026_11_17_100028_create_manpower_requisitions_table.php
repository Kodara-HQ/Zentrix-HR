<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manpower_requisitions', function (Blueprint $table) {
            $table->string('id', 30)->primary();
            $table->string('department_id');
            $table->string('job_title_id');
            $table->string('location_id')->nullable();
            $table->integer('headcount');
            $table->text('justification');
            $table->string('status', 40)->default('PENDING');
            $table->string('requested_by_id')->nullable();
            $table->text('decision_note')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manpower_requisitions');
    }
};
