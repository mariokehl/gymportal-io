<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration für Widerrufsfunktion gemäß § 356a BGB
 *
 * Ab 19. Juni 2026 verpflichtend für alle Online-Verträge.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Füge 'pending' zum Status-Enum hinzu (falls nicht bereits vorhanden)
        // und füge 'withdrawn' als neuen Status hinzu
        Schema::table('memberships', function (Blueprint $table) {
            // Widerrufs-Felder
            $table->timestamp('withdrawn_at')->nullable()->after('cancellation_reason');
            $table->string('withdrawal_confirmation_sent_to')->nullable()->after('withdrawn_at');
            $table->decimal('withdrawal_refund_amount', 10, 2)->nullable()->after('withdrawal_confirmation_sent_to');
            $table->timestamp('withdrawal_refund_processed_at')->nullable()->after('withdrawal_refund_amount');
        });

        // Status-Enum um 'withdrawn' erweitern
        DB::statement("ALTER TABLE memberships MODIFY COLUMN status ENUM('active', 'paused', 'cancelled', 'expired', 'pending', 'withdrawn') DEFAULT 'active'");
    }

    public function down(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->dropColumn([
                'withdrawn_at',
                'withdrawal_confirmation_sent_to',
                'withdrawal_refund_amount',
                'withdrawal_refund_processed_at',
            ]);
        });

        // Status-Enum zurücksetzen
        DB::statement("ALTER TABLE memberships MODIFY COLUMN status ENUM('active', 'paused', 'cancelled', 'expired', 'pending') DEFAULT 'active'");
    }
};
