<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->string('id', 30)->primary();
            $table->string('user_id');
            $table->string('title');
            $table->text('body');
            $table->string('type');
            $table->string('link')->nullable();
            $table->dateTime('read_at')->nullable();
            $table->string('dedupe_key')->nullable()->unique();
            $table->dateTime('created_at')->useCurrent();
            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
