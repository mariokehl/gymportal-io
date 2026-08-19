<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the printed check-in station: a sheet of paper at the counter carrying a
 * static QR code that members scan with their own phone, for gyms that do not
 * want scanner hardware.
 *
 * The token identifies the *station*, never the member — who is checking in is
 * decided solely by the PWA session bearing the request. A photographed code is
 * therefore useless on its own.
 *
 * Defaults to off, so no existing gym changes behaviour until an operator
 * opts in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gyms', function (Blueprint $table) {
            $table->boolean('checkin_station_enabled')
                ->default(false)
                ->after('cross_location_checkin_rule');

            // Nullable: only generated once a gym actually enables the station.
            $table->string('checkin_station_token', 64)
                ->nullable()
                ->unique()
                ->after('checkin_station_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('gyms', function (Blueprint $table) {
            $table->dropColumn([
                'checkin_station_enabled',
                'checkin_station_token',
            ]);
        });
    }
};
