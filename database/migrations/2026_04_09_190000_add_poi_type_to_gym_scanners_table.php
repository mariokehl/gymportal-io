<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Fügt poi_type zu gym_scanners hinzu, um zwischen Eingangs-Scannern
     * und Solarium-Scannern zu unterscheiden. Das Solarium-Scanner pollt
     * auf pending redemptions und steuert das Shelly Relay.
     */
    public function up(): void
    {
        Schema::table('gym_scanners', function (Blueprint $table) {
            $table->string('poi_type', 20)->default('entrance')->after('device_name');
        });
    }

    public function down(): void
    {
        Schema::table('gym_scanners', function (Blueprint $table) {
            $table->dropColumn('poi_type');
        });
    }
};
