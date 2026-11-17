<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rating_scales', function (Blueprint $table) {
            $table->string('id', 30)->primary();
            $table->string('name')->unique();
            $table->integer('min');
            $table->integer('max');
            $table->json('labels');
            $table->boolean('is_default')->default(false);
            $table->dateTime('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rating_scales');
    }
};
