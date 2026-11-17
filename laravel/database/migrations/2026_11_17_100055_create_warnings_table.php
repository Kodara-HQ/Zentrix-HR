<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warnings', function (Blueprint $table) {
            $table->string('id', 30)->primary();
            $table->string('case_id')->nullable();
            $table->string('employee_id');
            $table->string('type', 40);
            $table->text('content');
            $table->date('issued_on');
            $table->string('issued_by_id')->nullable();
            $table->dateTime('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warnings');
    }
};
