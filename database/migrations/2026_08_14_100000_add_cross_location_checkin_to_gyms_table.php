<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the per-location rule that decides which members of the organisation may
 * check in at this gym.
 *
 * The default 'own' reproduces today's behaviour exactly — every existing gym
 * keeps accepting only its own members until an operator opts in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gyms', function (Blueprint $table) {
            $table->string('cross_location_checkin_rule', 20)
                ->default('own')
                ->after('scanner_secret_key');
        });
    }

    public function down(): void
    {
        Schema::table('gyms', function (Blueprint $table) {
            $table->dropColumn('cross_location_checkin_rule');
        });
    }
};
