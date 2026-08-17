<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $newTypes = ['qr_code', 'nfc_card', 'rolling_qr'];

    private array $oldTypes = ['qr_code', 'nfc_card'];

    /**
     * Run the migrations.
     *
     * Extends the scan_type ENUM by 'rolling_qr'.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            $typeList = "'".implode("', '", $this->newTypes)."'";
            DB::statement('ALTER TABLE scanner_access_logs DROP CONSTRAINT IF EXISTS scanner_access_logs_scan_type_check');
            DB::statement("ALTER TABLE scanner_access_logs ADD CONSTRAINT scanner_access_logs_scan_type_check CHECK (scan_type IN ({$typeList}))");
        } elseif ($driver === 'sqlite') {
            // SQLite has no ENUM, but Laravel renders enum() as a CHECK
            // constraint — so the column has to be rebuilt to widen it.
            $this->rebuildSqliteColumn($this->newTypes);
        } else {
            $typeList = "'".implode("', '", $this->newTypes)."'";
            DB::statement("ALTER TABLE scanner_access_logs MODIFY scan_type ENUM({$typeList}) NOT NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        // Rewrite existing rolling_qr entries to qr_code
        DB::table('scanner_access_logs')
            ->where('scan_type', 'rolling_qr')
            ->update(['scan_type' => 'qr_code']);

        if ($driver === 'pgsql') {
            $typeList = "'".implode("', '", $this->oldTypes)."'";
            DB::statement('ALTER TABLE scanner_access_logs DROP CONSTRAINT IF EXISTS scanner_access_logs_scan_type_check');
            DB::statement("ALTER TABLE scanner_access_logs ADD CONSTRAINT scanner_access_logs_scan_type_check CHECK (scan_type IN ({$typeList}))");
        } elseif ($driver === 'sqlite') {
            $this->rebuildSqliteColumn($this->oldTypes);
        } else {
            $typeList = "'".implode("', '", $this->oldTypes)."'";
            DB::statement("ALTER TABLE scanner_access_logs MODIFY scan_type ENUM({$typeList}) NOT NULL");
        }
    }

    /**
     * Re-create the scan_type column so its CHECK constraint accepts the given
     * values. SQLite cannot alter a constraint in place.
     */
    private function rebuildSqliteColumn(array $types): void
    {
        Schema::table('scanner_access_logs', function (Blueprint $table) {
            $table->string('scan_type_tmp', 20)->nullable()->after('scan_type');
        });

        DB::statement('UPDATE scanner_access_logs SET scan_type_tmp = scan_type');

        Schema::table('scanner_access_logs', function (Blueprint $table) {
            $table->dropColumn('scan_type');
        });

        Schema::table('scanner_access_logs', function (Blueprint $table) use ($types) {
            $table->enum('scan_type', $types)->nullable()->after('member_id');
        });

        DB::statement('UPDATE scanner_access_logs SET scan_type = scan_type_tmp');

        Schema::table('scanner_access_logs', function (Blueprint $table) {
            $table->dropColumn('scan_type_tmp');
        });
    }
};
