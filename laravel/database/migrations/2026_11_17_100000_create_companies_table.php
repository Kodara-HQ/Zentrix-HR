<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->string('id', 30)->primary();
            $table->string('name');
            $table->string('legal_name');
            $table->string('tin')->nullable();
            $table->string('ssnit_employer_no')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->default('Ghana');
            $table->string('currency')->default('GHS');
            $table->integer('next_employee_no')->default(1);
            $table->dateTime('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
