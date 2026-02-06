<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Neue Spalten in gyms Tabelle
        Schema::table('gyms', function (Blueprint $table) {
            $table->boolean('contracts_start_first_of_month')->default(false)->after('widget_settings');
            $table->string('free_trial_membership_name')->default('Gratis-Testzeitraum')->after('contracts_start_first_of_month');
        });

        // Spalte für Verknüpfung mit Gratis-Mitgliedschaft
        Schema::table('memberships', function (Blueprint $table) {
            $table->unsignedBigInteger('linked_free_membership_id')->nullable()->after('notes');
            $table->foreign('linked_free_membership_id')
                ->references('id')
                ->on('memberships')
                ->nullOnDelete();
        });

        // Marker für Gratis-Plan
        Schema::table('membership_plans', function (Blueprint $table) {
            $table->boolean('is_free_trial_plan')->default(false)->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('membership_plans', function (Blueprint $table) {
            $table->dropColumn('is_free_trial_plan');
        });

        Schema::table('memberships', function (Blueprint $table) {
            $table->dropForeign(['linked_free_membership_id']);
            $table->dropColumn('linked_free_membership_id');
        });

        Schema::table('gyms', function (Blueprint $table) {
            $table->dropColumn(['contracts_start_first_of_month', 'free_trial_membership_name']);
        });
    }
};
