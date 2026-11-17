<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_feedbacks', function (Blueprint $table) {
            $table->string('id', 30)->primary();
            $table->string('employee_id')->nullable();
            $table->boolean('anonymous')->default(false);
            $table->string('category');
            $table->text('message');
            $table->dateTime('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_feedbacks');
    }
};
