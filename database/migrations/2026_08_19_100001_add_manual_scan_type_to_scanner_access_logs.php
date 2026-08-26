<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Makes the access log able to hold check-ins from the printed station, where
 * the member's own phone takes the place of a scanner device.
 *
 * Two changes: 'manual' joins the scan_type ENUM, and device_number becomes
 * nullable — a sheet of paper has no device number, and inventing one would
 * make it collide with a real scanner in the live log's device filter.
 *
 * Follows the same driver-by-driver shape as the rolling_qr migration, since
 * only MySQL has a real ENUM to widen.
 */
return new class extends Migration
{
    private array $newTypes = ['qr_code', 'nfc_card', 'rolling_qr', 'manual'];

    private array $oldTypes = ['qr_code', 'nfc_card', 'rolling_qr'];

    public function up(): void
    {
        $this->setScanTypes($this->newTypes);

        Schema::table('scanner_access_logs', function (Blueprint $table) {
            $table->string('device_number', 10)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Station rows have no device number and no longer-valid scan type;
        // dropping them is the only way back to a schema that excludes them.
        DB::table('scanner_access_logs')->where('scan_type', 'manual')->delete();

        Schema::table('scanner_access_logs', function (Blueprint $table) {
            $table->string('device_number', 10)->nullable(false)->change();
        });

        $this->setScanTypes($this->oldTypes);
    }

    /**
     * Restrict scan_type to exactly $types, whichever way the driver expresses
     * that restriction.
     *
     * @param  array<int, string>  $types
     */
    private function setScanTypes(array $types): void
    {
        $driver = Schema::getConnection()->getDriverName();
        $typeList = "'".implode("', '", $types)."'";

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE scanner_access_logs DROP CONSTRAINT IF EXISTS scanner_access_logs_scan_type_check');
            DB::statement("ALTER TABLE scanner_access_logs ADD CONSTRAINT scanner_access_logs_scan_type_check CHECK (scan_type IN ({$typeList}))");

            return;
        }

        if ($driver === 'sqlite') {
            $this->rebuildSqliteColumn($types);

            return;
        }

        DB::statement("ALTER TABLE scanner_access_logs MODIFY scan_type ENUM({$typeList}) NOT NULL");
    }

    /**
     * Re-create the scan_type column so its CHECK constraint accepts the given
     * values. SQLite cannot alter a constraint in place.
     *
     * @param  array<int, string>  $types
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
