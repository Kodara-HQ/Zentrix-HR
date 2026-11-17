<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_offers', function (Blueprint $table) {
            $table->string('id', 30)->primary();
            $table->string('applicant_id');
            $table->decimal('salary', 14, 2);
            $table->date('start_date');
            $table->string('status', 40)->default('DRAFT');
            $table->text('notes')->nullable();
            $table->date('expires_on')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_offers');
    }
};
