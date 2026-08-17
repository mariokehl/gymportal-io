<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the contract-side half of the cross-location rule: at which locations
 * members holding this plan may check in.
 *
 * Default 'own' keeps every existing contract restricted to the member's home
 * location, which is what the system does today.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_plans', function (Blueprint $table) {
            $table->string('location_scope', 20)
                ->default('own')
                ->after('gym_id');
        });
    }

    public function down(): void
    {
        Schema::table('membership_plans', function (Blueprint $table) {
            $table->dropColumn('location_scope');
        });
    }
};
