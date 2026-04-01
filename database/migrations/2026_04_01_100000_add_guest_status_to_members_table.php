<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        switch ($driver) {
            case 'mariadb':
            case 'mysql':
                $columns = DB::select("SHOW COLUMNS FROM members LIKE 'status'");

                if (!empty($columns)) {
                    $currentType = $columns[0]->Type;

                    if (strpos($currentType, 'extern') === false) {
                        DB::statement("ALTER TABLE members MODIFY COLUMN status ENUM('active', 'inactive', 'paused', 'overdue', 'pending', 'blocked', 'extern') NOT NULL DEFAULT 'active'");
                    }
                }
                break;

            case 'pgsql':
                DB::statement('ALTER TABLE members DROP CONSTRAINT IF EXISTS members_status_check');
                DB::statement("ALTER TABLE members ADD CONSTRAINT members_status_check CHECK (status IN ('active', 'inactive', 'paused', 'overdue', 'pending', 'blocked', 'extern'))");
                break;

            case 'sqlite':
                logger()->info('SQLite detected - ENUM update skipped (no strict enum enforcement)');
                break;
        }
    }

    public function down(): void
    {
        $guestCount = DB::table('members')->where('status', 'extern')->count();

        if ($guestCount > 0) {
            throw new \Exception("Cannot rollback: {$guestCount} members with 'extern' status exist. Please resolve these first.");
        }

        $driver = DB::getDriverName();

        switch ($driver) {
            case 'mariadb':
            case 'mysql':
                DB::statement("ALTER TABLE members MODIFY COLUMN status ENUM('active', 'inactive', 'paused', 'overdue', 'pending', 'blocked') NOT NULL DEFAULT 'active'");
                break;

            case 'pgsql':
                DB::statement('ALTER TABLE members DROP CONSTRAINT IF EXISTS members_status_check');
                DB::statement("ALTER TABLE members ADD CONSTRAINT members_status_check CHECK (status IN ('active', 'inactive', 'paused', 'overdue', 'pending', 'blocked'))");
                break;

            case 'sqlite':
                logger()->info('SQLite detected - ENUM revert skipped');
                break;
        }
    }
};
