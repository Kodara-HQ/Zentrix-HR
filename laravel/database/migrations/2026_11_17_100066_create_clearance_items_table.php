<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clearance_items', function (Blueprint $table) {
            $table->string('id', 30)->primary();
            $table->string('separation_id');
            $table->string('item');
            $table->boolean('returned')->default(false);
            $table->string('cleared_by_id')->nullable();
            $table->text('notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clearance_items');
    }
};
