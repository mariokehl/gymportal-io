<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds the hashed counterpart of api_token and backfills it for every
     * existing device. The plaintext column stays for now: scanners in the
     * field keep authenticating through it, and the operator can still
     * download a device config. Dropping it is a separate, later migration
     * once every device has been re-provisioned.
     */
    public function up(): void
    {
        Schema::table('gym_scanners', function (Blueprint $table) {
            $table->string('api_token_hash', 64)->nullable()->after('api_token');
            $table->index('api_token_hash');
        });

        // Backfill in chunks — a gym can run many devices and this must not
        // load the whole table into memory on a production database.
        DB::table('gym_scanners')
            ->select('id', 'api_token')
            ->whereNotNull('api_token')
            ->orderBy('id')
            ->chunk(500, function ($scanners) {
                foreach ($scanners as $scanner) {
                    DB::table('gym_scanners')
                        ->where('id', $scanner->id)
                        ->update(['api_token_hash' => hash('sha256', $scanner->api_token)]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gym_scanners', function (Blueprint $table) {
            $table->dropIndex(['api_token_hash']);
            $table->dropColumn('api_token_hash');
        });
    }
};
