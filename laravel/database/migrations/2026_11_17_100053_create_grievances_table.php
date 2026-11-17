<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grievances', function (Blueprint $table) {
            $table->string('id', 30)->primary();
            $table->string('employee_id');
            $table->string('against_employee_id')->nullable();
            $table->string('category');
            $table->text('description');
            $table->string('status', 40)->default('OPEN');
            $table->text('resolution')->nullable();
            $table->boolean('confidential')->default(true);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grievances');
    }
};
