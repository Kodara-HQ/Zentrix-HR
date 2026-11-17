<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_locations', function (Blueprint $table) {
            $table->string('id', 30)->primary();
            $table->string('name');
            $table->string('branch');
            $table->string('city');
            $table->string('region');
            $table->text('address')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at');
            $table->unique(['name', 'branch']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_locations');
    }
};
