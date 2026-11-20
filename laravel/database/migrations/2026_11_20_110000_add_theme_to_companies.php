<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Each company's colours, chosen by the system administrator (empty means the standard Zentrix colours). */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('companies', 'theme_primary')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->string('theme_primary', 7)->nullable();
                $table->string('theme_accent', 7)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['theme_primary', 'theme_accent']);
        });
    }
};
