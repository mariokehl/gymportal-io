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
        $this->updateMembershipStatusEnum();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Prüfen ob 'pending' Status verwendet wird
        $pendingCount = DB::table('members')->where('status', 'pending')->count();

        if ($pendingCount > 0) {
            throw new \Exception("Cannot rollback: {$pendingCount} members with 'pending' status exist. Please resolve these first.");
        }

        $this->revertMemberStatusEnum();
    }

    /**
     * Member Status ENUM erweitern
     */
    private function updateMembershipStatusEnum(): void
    {
        $driver = DB::getDriverName();

        switch ($driver) {
            case 'mariadb':
            case 'mysql':
                // Aktueller Status der members Tabelle ermitteln
                $columns = DB::select("SHOW COLUMNS FROM memberships LIKE 'status'");

                if (!empty($columns)) {
                    $currentType = $columns[0]->Type;

                    // Prüfen ob 'pending' bereits vorhanden ist
                    if (strpos($currentType, 'pending') === false) {
                        DB::statement("ALTER TABLE memberships MODIFY COLUMN status ENUM('active', 'paused', 'cancelled', 'expired', 'pending') NOT NULL DEFAULT 'active'");
                    }
                } else {
                    // Fallback falls Spalte noch nicht als ENUM existiert
                    Schema::table('memberships', function (Blueprint $table) {
                        $table->enum('status', ['active', 'paused', 'cancelled', 'expired', 'pending'])->default('active')->change();
                    });
                }
                break;

            case 'pgsql':
                // Prüfen ob der ENUM-Typ existiert
                $enumExists = DB::select("SELECT 1 FROM pg_type WHERE typname = 'membership_status'");

                if (empty($enumExists)) {
                    // ENUM-Typ erstellen falls nicht vorhanden
                    DB::statement("CREATE TYPE membership_status AS ENUM('active', 'paused', 'cancelled', 'expired', 'pending')");
                    DB::statement("ALTER TABLE memberships ALTER COLUMN status TYPE membership_status USING status::membership_status");
                } else {
                    // Prüfen ob 'pending' bereits existiert
                    $pendingExists = DB::select("SELECT 1 FROM pg_enum WHERE enumlabel = 'pending' AND enumtypid = (SELECT oid FROM pg_type WHERE typname = 'membership_status')");

                    if (empty($pendingExists)) {
                        DB::statement("ALTER TYPE membership_status ADD VALUE 'pending'");
                    }
                }
                break;

            case 'sqlite':
                // SQLite verwendet keine echten ENUMs
                // Normalerweise würde hier ein CHECK constraint aktualisiert werden
                logger()->info('SQLite detected - consider using string column for status instead of ENUM');

                // Für SQLite könnten wir den CHECK constraint aktualisieren
                // Dies ist aber komplex und erfordert Tabellen-Neuerstellung
                break;

            default:
                logger()->warning("Unknown database driver: {$driver}. Status ENUM update skipped.");
        }
    }

    /**
     * Member Status ENUM zurücksetzen
     */
    private function revertMemberStatusEnum(): void
    {
        $driver = DB::getDriverName();

        switch ($driver) {
            case 'mariadb':
            case 'mysql':
                DB::statement("ALTER TABLE memberships MODIFY COLUMN status ENUM('active', 'paused', 'cancelled', 'expired') NOT NULL DEFAULT 'active'");
                break;

            case 'pgsql':
                // PostgreSQL kann ENUM-Werte nicht einfach entfernen
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
