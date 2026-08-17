<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The pilot gyms all run a single scanner at the outside wall; the
     * check-out happens in software, not at a second device. Plain check-in is
     * therefore the realistic default for a newly created device.
     *
     * Only the column default changes — existing devices keep the task they
     * were configured with, including any deliberate 'checkin_checkout'.
     */
    public function up(): void
    {
        Schema::table('gym_scanners', function (Blueprint $table) {
            $table->string('device_task', 20)->default('checkin')->change();
        });
    }

    public function down(): void
    {
        Schema::table('gym_scanners', function (Blueprint $table) {
            $table->string('device_task', 20)->default('checkin_checkout')->change();
        });
    }
};
