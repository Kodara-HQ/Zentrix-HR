<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->string('id', 30)->primary();
            $table->string('user_id')->nullable();
            $table->string('action');
            $table->string('entity');
            $table->string('entity_id')->nullable();
            $table->text('summary');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->index(['entity', 'entity_id']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
