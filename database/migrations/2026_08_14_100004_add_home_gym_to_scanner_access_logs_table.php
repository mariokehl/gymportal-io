<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records the home location of the scanned member on the log row itself.
 *
 * Denormalised on purpose: the live log has to tell a visitor from an own
 * member, and joining members at read time would break for deleted members and
 * silently rewrite history when a member is moved to another location.
 * Null on existing rows, which the frontend reads as "own member" — correct,
 * since cross-location check-ins were impossible before this feature.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scanner_access_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('home_gym_id')->nullable()->after('member_id');
            $table->index(['gym_id', 'home_gym_id']);
        });
    }

    public function down(): void
    {
        Schema::table('scanner_access_logs', function (Blueprint $table) {
            $table->dropIndex(['gym_id', 'home_gym_id']);
            $table->dropColumn('home_gym_id');
        });
    }
};
