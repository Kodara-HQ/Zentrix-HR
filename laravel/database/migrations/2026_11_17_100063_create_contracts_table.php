<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->string('id', 30)->primary();
            $table->string('employee_id');
            $table->string('type');
            $table->string('title');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('status')->default('ACTIVE');
            $table->text('notes')->nullable();
            $table->string('file_asset_id')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at');
            $table->index(['end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
