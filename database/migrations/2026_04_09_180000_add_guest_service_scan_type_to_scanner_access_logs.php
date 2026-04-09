<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $newTypes = ['qr_code', 'nfc_card', 'rolling_qr', 'guest_service'];
    private array $oldTypes = ['qr_code', 'nfc_card', 'rolling_qr'];

    /**
     * Run the migrations.
     *
     * Erweitert scan_type ENUM um 'guest_service'.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            $typeList = "'" . implode("', '", $this->newTypes) . "'";
            DB::statement("ALTER TABLE scanner_access_logs DROP CONSTRAINT IF EXISTS scanner_access_logs_scan_type_check");
            DB::statement("ALTER TABLE scanner_access_logs ADD CONSTRAINT scanner_access_logs_scan_type_check CHECK (scan_type IN ({$typeList}))");
        } else {
            $typeList = "'" . implode("', '", $this->newTypes) . "'";
            DB::statement("ALTER TABLE scanner_access_logs MODIFY scan_type ENUM({$typeList}) NOT NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        DB::table('scanner_access_logs')
            ->where('scan_type', 'guest_service')
            ->update(['scan_type' => 'qr_code']);

        if ($driver === 'pgsql') {
            $typeList = "'" . implode("', '", $this->oldTypes) . "'";
            DB::statement("ALTER TABLE scanner_access_logs DROP CONSTRAINT IF EXISTS scanner_access_logs_scan_type_check");
            DB::statement("ALTER TABLE scanner_access_logs ADD CONSTRAINT scanner_access_logs_scan_type_check CHECK (scan_type IN ({$typeList}))");
        } else {
            $typeList = "'" . implode("', '", $this->oldTypes) . "'";
            DB::statement("ALTER TABLE scanner_access_logs MODIFY scan_type ENUM({$typeList}) NOT NULL");
        }
    }
};
