<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $newCycles = ['monthly', 'quarterly', 'biannual', 'yearly'];

    private array $oldCycles = ['monthly', 'quarterly', 'yearly'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            $cycleList = "'".implode("', '", $this->newCycles)."'";
            DB::statement('ALTER TABLE membership_plans DROP CONSTRAINT IF EXISTS membership_plans_billing_cycle_check');
            DB::statement("ALTER TABLE membership_plans ADD CONSTRAINT membership_plans_billing_cycle_check CHECK (billing_cycle IN ({$cycleList}))");
        } elseif ($driver === 'sqlite') {
            // SQLite: ENUMs are not enforced; nothing to do.
        } else {
            $cycleList = "'".implode("', '", $this->newCycles)."'";
            DB::statement("ALTER TABLE membership_plans MODIFY COLUMN billing_cycle ENUM({$cycleList}) NOT NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            $cycleList = "'".implode("', '", $this->oldCycles)."'";
            DB::statement('ALTER TABLE membership_plans DROP CONSTRAINT IF EXISTS membership_plans_billing_cycle_check');
            DB::statement("ALTER TABLE membership_plans ADD CONSTRAINT membership_plans_billing_cycle_check CHECK (billing_cycle IN ({$cycleList}))");
        } elseif ($driver === 'sqlite') {
            // SQLite: ENUMs are not enforced; nothing to do.
        } else {
            $cycleList = "'".implode("', '", $this->oldCycles)."'";
            DB::statement("ALTER TABLE membership_plans MODIFY COLUMN billing_cycle ENUM({$cycleList}) NOT NULL");
        }
    }
};
