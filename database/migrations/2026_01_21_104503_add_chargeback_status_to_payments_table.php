<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $newStatuses = ['pending', 'paid', 'failed', 'refunded', 'partially_refunded', 'chargeback', 'expired', 'canceled'];
    private array $oldStatuses = ['pending', 'paid', 'failed', 'refunded'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            // PostgreSQL: Laravel uses CHECK constraints for enum columns
            $statusList = "'" . implode("', '", $this->newStatuses) . "'";
            DB::statement("ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_status_check");
            DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_status_check CHECK (status IN ({$statusList}))");
        } else {
            // MySQL/MariaDB: Modify the enum column
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
            // PostgreSQL: Restore original CHECK constraint
            $statusList = "'" . implode("', '", $this->oldStatuses) . "'";
            DB::statement("ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_status_check");
            DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_status_check CHECK (status IN ({$statusList}))");
        } else {
            // MySQL/MariaDB: Restore original enum
            $statusList = "'" . implode("', '", $this->oldStatuses) . "'";
            DB::statement("ALTER TABLE payments MODIFY COLUMN status ENUM({$statusList}) DEFAULT 'pending'");
        }
    }
};
