<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vacancies', function (Blueprint $table) {
            $table->string('id', 30)->primary();
            $table->string('requisition_id')->nullable();
            $table->string('title');
            $table->string('department_id');
            $table->string('job_title_id');
            $table->string('location_id');
            $table->text('description');
            $table->text('requirements');
            $table->integer('openings')->default(1);
            $table->integer('filled')->default(0);
            $table->string('status', 40)->default('DRAFT');
            $table->decimal('salary_min', 14, 2)->nullable();
            $table->decimal('salary_max', 14, 2)->nullable();
            $table->decimal('advertising_cost', 14, 2)->default(0);
            $table->decimal('agency_cost', 14, 2)->default(0);
            $table->date('opened_on')->nullable();
            $table->date('closed_on')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vacancies');
    }
};
