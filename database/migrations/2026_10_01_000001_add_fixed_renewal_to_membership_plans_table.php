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
     * Contracts concluded before 01.03.2022 (Gesetz für faire Verbraucherverträge)
     * may still renew by a fixed term of more than one month. 'fixed' covers
     * these legacy contracts, renewal_months holds the length of that term.
     */
    public function up(): void
    {
        Schema::table('membership_plans', function (Blueprint $table) {
            $table->enum('auto_renew_type', ['indefinite', 'monthly', 'fixed'])
                ->default('indefinite')
                ->comment('Verlängerungsart nach Erstlaufzeit: indefinite=unbefristet, monthly=monatlich rollierend, fixed=feste Laufzeit (nur Altverträge vor 01.03.2022)')
                ->change();

            $table->unsignedTinyInteger('renewal_months')
                ->nullable()
                ->after('auto_renew_type')
                ->comment('Verlängerungslaufzeit in Monaten bei auto_renew_type=fixed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('membership_plans')
            ->where('auto_renew_type', 'fixed')
            ->update(['auto_renew_type' => 'monthly']);

        Schema::table('membership_plans', function (Blueprint $table) {
            $table->dropColumn('renewal_months');

            $table->enum('auto_renew_type', ['indefinite', 'monthly'])
                ->default('indefinite')
                ->comment('Verlängerungsart nach Erstlaufzeit: indefinite=unbefristet, monthly=monatlich rollierend')
                ->change();
        });
    }
};
