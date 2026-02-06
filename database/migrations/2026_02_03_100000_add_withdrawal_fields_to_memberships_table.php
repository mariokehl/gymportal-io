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
        $this->updateMembershipStatusEnum();
    }

    public function down(): void
    {
        // Prüfen ob 'withdrawn' Status verwendet wird
        $withdrawnCount = DB::table('memberships')->where('status', 'withdrawn')->count();

        if ($withdrawnCount > 0) {
            throw new \Exception("Cannot rollback: {$withdrawnCount} memberships with 'withdrawn' status exist. Please resolve these first.");
        }

        Schema::table('memberships', function (Blueprint $table) {
            $table->dropColumn([
                'withdrawn_at',
                'withdrawal_confirmation_sent_to',
                'withdrawal_refund_amount',
                'withdrawal_refund_processed_at',
            ]);
        });

        // Status-Enum zurücksetzen
        $this->revertMembershipStatusEnum();
    }

    private function updateMembershipStatusEnum(): void
    {
        $driver = DB::getDriverName();

        switch ($driver) {
            case 'mariadb':
            case 'mysql':
                DB::statement("ALTER TABLE memberships MODIFY COLUMN status ENUM('active', 'paused', 'cancelled', 'expired', 'pending', 'withdrawn') NOT NULL DEFAULT 'active'");
                break;

            case 'pgsql':
                DB::statement('ALTER TABLE memberships DROP CONSTRAINT IF EXISTS memberships_status_check');
                DB::statement("ALTER TABLE memberships ADD CONSTRAINT memberships_status_check CHECK (status IN ('active', 'paused', 'cancelled', 'expired', 'pending', 'withdrawn'))");
                break;

            case 'sqlite':
                logger()->info('SQLite detected - consider using string column for status instead of ENUM');
                break;

            default:
                logger()->warning("Unknown database driver: {$driver}. Status ENUM update skipped.");
        }
    }

    private function revertMembershipStatusEnum(): void
    {
        $driver = DB::getDriverName();

        switch ($driver) {
            case 'mariadb':
            case 'mysql':
                DB::statement("ALTER TABLE memberships MODIFY COLUMN status ENUM('active', 'paused', 'cancelled', 'expired', 'pending') NOT NULL DEFAULT 'active'");
                break;

            case 'pgsql':
                DB::statement('ALTER TABLE memberships DROP CONSTRAINT IF EXISTS memberships_status_check');
                DB::statement("ALTER TABLE memberships ADD CONSTRAINT memberships_status_check CHECK (status IN ('active', 'paused', 'cancelled', 'expired', 'pending'))");
                break;

            case 'sqlite':
                logger()->info('SQLite detected - ENUM revert skipped');
                break;

            default:
                logger()->warning("Unknown database driver: {$driver}. ENUM revert skipped.");
        }
    }
};
