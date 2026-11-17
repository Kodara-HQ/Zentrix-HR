<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_programs', function (Blueprint $table) {
            $table->string('id', 30)->primary();
            $table->string('title');
            $table->string('provider_id')->nullable();
            $table->text('description')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('cost', 14, 2)->default(0);
            $table->string('location')->nullable();
            $table->integer('capacity')->nullable();
            $table->string('status', 40)->default('PLANNED');
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_programs');
    }
};
