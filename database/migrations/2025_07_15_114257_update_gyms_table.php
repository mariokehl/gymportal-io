<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gyms', function (Blueprint $table) {
            // Füge trial_ends_at Feld hinzu falls noch nicht vorhanden
            if (!Schema::hasColumn('gyms', 'trial_ends_at')) {
                $table->timestamp('trial_ends_at')->nullable()->after('subscription_ends_at');
            }

            // Stelle sicher, dass alle billing-relevanten Felder existieren
            if (!Schema::hasColumn('gyms', 'paddle_subscription_id')) {
                $table->string('paddle_subscription_id')->nullable()->after('owner_id');
            }

            if (!Schema::hasColumn('gyms', 'subscription_status')) {
                $table->string('subscription_status')->nullable()->after('paddle_subscription_id');
            }

            if (!Schema::hasColumn('gyms', 'subscription_plan')) {
                $table->string('subscription_plan')->nullable()->after('subscription_status');
            }

            if (!Schema::hasColumn('gyms', 'subscription_ends_at')) {
                $table->timestamp('subscription_ends_at')->nullable()->after('subscription_plan');
            }
        });

        // Entferne Standardwerte und setze alle Subscription-Felder zurück
        DB::statement('ALTER TABLE `gyms` MODIFY COLUMN `subscription_status` VARCHAR(255) NULL DEFAULT NULL');
        DB::statement('ALTER TABLE `gyms` MODIFY COLUMN `subscription_plan` VARCHAR(255) NULL DEFAULT NULL');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gyms', function (Blueprint $table) {
            $table->dropColumn('trial_ends_at');
        });

        // Wiederherstellen der ursprünglichen Standardwerte
        DB::statement('ALTER TABLE `gyms` MODIFY COLUMN `subscription_status` VARCHAR(255) NOT NULL DEFAULT "active"');
        DB::statement('ALTER TABLE `gyms` MODIFY COLUMN `subscription_plan` VARCHAR(255) NOT NULL DEFAULT "free"');
    }
};
