<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A device is no longer just a door scanner: it now declares what job it
     * does (check-in, check-out, dispenser, area control, manual). Dispenser
     * and area-control devices additionally reference the usage add-on whose
     * quota they settle against.
     */
    public function up(): void
    {
        Schema::table('gym_scanners', function (Blueprint $table) {
            $table->string('device_task', 20)
                ->default('checkin_checkout')
                ->after('device_name');

            $table->foreignId('addon_id')
                ->nullable()
                ->after('device_task')
                ->constrained('addons')
                ->nullOnDelete();

            // Whether the device blocks usage without quota, or only logs it.
            $table->boolean('enforce_quota')
                ->default(true)
                ->after('addon_id');

            $table->index(['gym_id', 'device_task']);
        });
    }

    public function down(): void
    {
        Schema::table('gym_scanners', function (Blueprint $table) {
            $table->dropIndex(['gym_id', 'device_task']);
            $table->dropConstrainedForeignId('addon_id');
            $table->dropColumn(['device_task', 'enforce_quota']);
        });
    }
};
