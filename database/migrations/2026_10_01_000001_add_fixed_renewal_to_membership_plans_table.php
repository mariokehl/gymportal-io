<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contracts concluded before 01.03.2022 (Gesetz für faire Verbraucherverträge)
 * may still renew by a fixed term of more than one month. 'fixed' covers
 * these legacy contracts, renewal_months holds the length of that term.
 *
 * PostgreSQL has no real ENUM — Laravel emulates it with a CHECK constraint,
 * and Blueprint::change() cannot alter that, so the constraint is swapped
 * by hand there.
 */
return new class extends Migration
{
    private array $newTypes = ['indefinite', 'monthly', 'fixed'];

    private array $oldTypes = ['indefinite', 'monthly'];

    private string $newComment = 'Verlängerungsart nach Erstlaufzeit: indefinite=unbefristet, monthly=monatlich rollierend, fixed=feste Laufzeit (nur Altverträge vor 01.03.2022)';

    private string $oldComment = 'Verlängerungsart nach Erstlaufzeit: indefinite=unbefristet, monthly=monatlich rollierend';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->setRenewTypes($this->newTypes, $this->newComment);

        Schema::table('membership_plans', function (Blueprint $table) {
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
        });

        $this->setRenewTypes($this->oldTypes, $this->oldComment);
    }

    /**
     * Restrict auto_renew_type to exactly $types, whichever way the driver
     * expresses that restriction.
     *
     * @param  array<int, string>  $types
     */
    private function setRenewTypes(array $types, string $comment): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            $typeList = "'".implode("', '", $types)."'";

            DB::statement('ALTER TABLE membership_plans DROP CONSTRAINT IF EXISTS membership_plans_auto_renew_type_check');
            DB::statement("ALTER TABLE membership_plans ADD CONSTRAINT membership_plans_auto_renew_type_check CHECK (auto_renew_type IN ({$typeList}))");
            DB::statement('COMMENT ON COLUMN membership_plans.auto_renew_type IS '.DB::getPdo()->quote($comment));

            return;
        }

        Schema::table('membership_plans', function (Blueprint $table) use ($types, $comment) {
            $table->enum('auto_renew_type', $types)
                ->default('indefinite')
                ->comment($comment)
                ->change();
        });
    }
};
