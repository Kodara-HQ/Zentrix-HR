<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->string('id', 30)->primary();
            $table->integer('year');
            $table->integer('month');
            $table->string('status', 40)->default('DRAFT');
            $table->string('processed_by')->nullable();
            $table->string('approved_by')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('total_gross', 16, 2)->default(0);
            $table->decimal('total_net', 16, 2)->default(0);
            $table->decimal('total_paye', 16, 2)->default(0);
            $table->decimal('total_ssnit', 16, 2)->default(0);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at');
            $table->unique(['year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_runs');
    }
};
