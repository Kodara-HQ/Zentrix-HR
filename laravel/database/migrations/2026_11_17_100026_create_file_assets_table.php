<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_assets', function (Blueprint $table) {
            $table->string('id', 30)->primary();
            $table->string('storage_key');
            $table->string('file_name');
            $table->string('mime_type');
            $table->integer('size');
            $table->string('category_id')->nullable();
            $table->string('owner_type');
            $table->string('owner_id');
            $table->boolean('confidential')->default(false);
            $table->string('uploaded_by_id')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->index(['owner_type', 'owner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_assets');
    }
};
