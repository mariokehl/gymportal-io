<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Counts wrong entries per issued login code, independent of the client IP,
 * so a code can no longer be guessed from many addresses in parallel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('login_codes', function (Blueprint $table) {
            $table->unsignedTinyInteger('failed_attempts')->default(0)->after('used_at');
        });
    }

    public function down(): void
    {
        Schema::table('login_codes', function (Blueprint $table) {
            $table->dropColumn('failed_attempts');
        });
    }
};
