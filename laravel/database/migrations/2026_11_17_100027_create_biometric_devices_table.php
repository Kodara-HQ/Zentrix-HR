<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biometric_devices', function (Blueprint $table) {
            $table->string('id', 30)->primary();
            $table->string('name');
            $table->string('location_id')->nullable();
            $table->string('api_key_hash');
            $table->boolean('is_active')->default(true);
            $table->dateTime('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biometric_devices');
    }
};
