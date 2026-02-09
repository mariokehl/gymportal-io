<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('gyms', function (Blueprint $table) {
            $table->boolean('rolling_qr_enabled')->default(false)->after('scanner_secret_key');
            $table->unsignedSmallInteger('rolling_qr_interval')->default(3)->after('rolling_qr_enabled');
            $table->unsignedSmallInteger('rolling_qr_tolerance_windows')->default(1)->after('rolling_qr_interval');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gyms', function (Blueprint $table) {
            $table->dropColumn(['rolling_qr_enabled', 'rolling_qr_interval', 'rolling_qr_tolerance_windows']);
        });
    }
};
