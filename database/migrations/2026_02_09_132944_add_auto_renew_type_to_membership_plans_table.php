<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Gesetz für faire Verbraucherverträge (ab 01.03.2022):
     * Nach der Erstlaufzeit darf sich ein Vertrag nur noch auf unbestimmte Zeit
     * verlängern (unbefristet) oder monatlich rollierend weitergeführt werden.
     */
    public function up(): void
    {
        Schema::table('membership_plans', function (Blueprint $table) {
            $table->enum('auto_renew_type', ['indefinite', 'monthly'])
                ->default('indefinite')
                ->after('cancellation_period_unit')
                ->comment('Verlängerungsart nach Erstlaufzeit: indefinite=unbefristet, monthly=monatlich rollierend');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('membership_plans', function (Blueprint $table) {
            $table->dropColumn('auto_renew_type');
        });
    }
};
