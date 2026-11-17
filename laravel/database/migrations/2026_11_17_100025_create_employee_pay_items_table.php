<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_pay_items', function (Blueprint $table) {
            $table->string('id', 30)->primary();
            $table->string('employee_id');
            $table->string('component_id');
            $table->decimal('amount', 14, 2);
            $table->unique(['employee_id', 'component_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_pay_items');
    }
};
