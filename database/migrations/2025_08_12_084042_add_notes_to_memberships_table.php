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
        // Felder für memberships Tabelle
        Schema::table('memberships', function (Blueprint $table) {
            // Prüfen und hinzufügen falls nicht vorhanden
            if (!Schema::hasColumn('memberships', 'notes')) {
                $table->text('notes')->nullable()->after('contract_file_path');
            }

            // Index für bessere Performance bei Status-Abfragen
            if (!Schema::hasIndex('memberships', 'memberships_status_index')) {
                $table->index('status');
            }

            if (!Schema::hasIndex('memberships', 'memberships_cancellation_date_index')) {
                $table->index('cancellation_date');
            }
        });

        // Felder für membership_plans Tabelle (falls nicht bereits vorhanden)
        Schema::table('membership_plans', function (Blueprint $table) {
            if (!Schema::hasColumn('membership_plans', 'commitment_months')) {
                $table->integer('commitment_months')->nullable()->after('billing_cycle')
                    ->comment('Mindestlaufzeit in Monaten');
            }

            if (!Schema::hasColumn('membership_plans', 'cancellation_period_days')) {
                $table->integer('cancellation_period_days')->nullable()->after('commitment_months')
                    ->comment('Kündigungsfrist in Tagen');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->dropIndex('memberships_cancellation_date_index');
            $table->dropIndex('memberships_status_index');
            $table->dropColumn('notes');
        });

        Schema::table('membership_plans', function (Blueprint $table) {
            $table->dropColumn('cancellation_period_days');
            $table->dropColumn('commitment_months');
        });
    }
};
