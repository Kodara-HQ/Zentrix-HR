<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_components', function (Blueprint $table) {
            $table->string('id', 30)->primary();
            $table->string('name')->unique();
            $table->string('code')->unique();
            $table->string('kind', 40);
            $table->boolean('taxable')->default(true);
            $table->text('description')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_components');
    }
};
