<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lets the access log hold scans of the guest service QR code (day pass,
 * visit card, solarium minutes).
 *
 * The full list of scan types is restated on purpose: on PostgreSQL the CHECK
 * constraint is replaced as a whole, so leaving out an existing type (e.g.
 * 'manual') would reject those rows.
 */
return new class extends Migration
{
    private array $newTypes = ['qr_code', 'nfc_card', 'rolling_qr', 'manual', 'guest_service'];

    private array $oldTypes = ['qr_code', 'nfc_card', 'rolling_qr', 'manual'];

    public function up(): void
    {
        $this->setScanTypes($this->newTypes);
    }

    public function down(): void
    {
        DB::table('scanner_access_logs')
            ->where('scan_type', 'guest_service')
            ->update(['scan_type' => 'qr_code']);

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
