<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_qualifications', function (Blueprint $table) {
            $table->string('id', 30)->primary();
            $table->string('employee_id');
            $table->string('name');
            $table->string('institution')->nullable();
            $table->integer('year')->nullable();
            $table->string('licence_no')->nullable();
            $table->date('expires_on')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_qualifications');
    }
};
