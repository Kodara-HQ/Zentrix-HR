<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_assignments', function (Blueprint $table) {
            $table->string('id', 30)->primary();
            $table->string('employee_id');
            $table->string('shift_id');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_assignments');
    }
};
