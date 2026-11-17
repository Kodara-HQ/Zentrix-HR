<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_configs', function (Blueprint $table) {
            $table->string('id', 30)->primary();
            $table->string('key')->unique();
            $table->string('label');
            $table->json('steps');
            $table->dateTime('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_configs');
    }
};
