<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $newStatuses = ['pending', 'paid', 'failed', 'refunded', 'partially_refunded', 'chargeback', 'expired', 'canceled', 'unknown'];
    private array $oldStatuses = ['pending', 'paid', 'failed', 'refunded', 'partially_refunded', 'chargeback', 'expired', 'canceled'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            $statusList = "'" . implode("', '", $this->newStatuses) . "'";
            DB::statement("ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_status_check");
            DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_status_check CHECK (status IN ({$statusList}))");
        } else {
            $statusList = "'" . implode("', '", $this->newStatuses) . "'";
            DB::statement("ALTER TABLE payments MODIFY COLUMN status ENUM({$statusList}) DEFAULT 'pending'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            $statusList = "'" . implode("', '", $this->oldStatuses) . "'";
            DB::statement("ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_status_check");
            DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_status_check CHECK (status IN ({$statusList}))");
        } else {
            $statusList = "'" . implode("', '", $this->oldStatuses) . "'";
            DB::statement("ALTER TABLE payments MODIFY COLUMN status ENUM({$statusList}) DEFAULT 'pending'");
        }
    }
};
