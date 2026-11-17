<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_settings', function (Blueprint $table) {
            $table->string('id', 30)->primary();
            $table->string('event_key')->unique();
            $table->string('label');
            $table->boolean('in_app')->default(true);
            $table->boolean('email')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_settings');
    }
};
