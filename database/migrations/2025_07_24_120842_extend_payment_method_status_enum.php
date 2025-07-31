<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Status ENUM erweitern um 'pending'
        $this->updatePaymentMethodStatusEnum();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Prüfen ob 'pending' Status verwendet wird
        $pendingCount = DB::table('payment_methods')->where('status', 'pending')->count();

        if ($pendingCount > 0) {
            throw new \Exception("Cannot rollback: {$pendingCount} payment methods with 'pending' status exist. Please resolve these first.");
        }

        $this->revertPaymentMethodStatusEnum();
    }

    /**
     * Status ENUM erweitern
     */
    private function updatePaymentMethodStatusEnum(): void
    {
        $driver = DB::getDriverName();

        switch ($driver) {
            case 'mariadb':
            case 'mysql':
                DB::statement("ALTER TABLE payment_methods MODIFY COLUMN status ENUM('active', 'expired', 'failed', 'pending') NOT NULL DEFAULT 'active'");
                break;

            case 'pgsql':
                // Prüfen ob der Wert bereits existiert
                $exists = DB::select("SELECT 1 FROM pg_enum WHERE enumlabel = 'pending' AND enumtypid = (SELECT oid FROM pg_type WHERE typname = 'payment_method_status')");

                if (empty($exists)) {
                    DB::statement("ALTER TYPE payment_method_status ADD VALUE 'pending'");
                }
                break;

            case 'sqlite':
                // SQLite verwendet keine echten ENUMs - normalerweise CHECK constraints
                // Hier würden wir den CHECK constraint aktualisieren
                logger()->info('SQLite detected - consider using string column for status instead of ENUM');
                break;

            default:
                logger()->warning("Unknown database driver: {$driver}. ENUM update skipped.");
        }
    }

    /**
     * Status ENUM zurücksetzen
     */
    private function revertPaymentMethodStatusEnum(): void
    {
        $driver = DB::getDriverName();

        switch ($driver) {
            case 'mariadb':
            case 'mysql':
                DB::statement("ALTER TABLE payment_methods MODIFY COLUMN status ENUM('active', 'expired', 'failed') NOT NULL DEFAULT 'active'");
                break;

            case 'pgsql':
                // PostgreSQL kann ENUM-Werte nicht einfach entfernen
                // Komplexerer Rollback erforderlich
                logger()->warning('PostgreSQL ENUM values cannot be easily removed. Manual intervention required.');
                break;

            case 'sqlite':
                logger()->info('SQLite detected - ENUM revert skipped');
                break;

            default:
                logger()->warning("Unknown database driver: {$driver}. ENUM revert skipped.");
        }
    }
};
