<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_letters', function (Blueprint $table) {
            $table->string('id', 30)->primary();
            $table->string('employee_id');
            $table->string('type', 40);
            $table->string('subject');
            $table->text('body');
            $table->date('issued_on');
            $table->string('file_asset_id')->nullable();
            $table->dateTime('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_letters');
    }
};
