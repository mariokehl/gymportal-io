<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Deleting a member used to keep the NFC tags of its access
        // configuration, because members are only soft deleted and the
        // cascade never fires. Release the tags still held by deleted members
        // so they can be assigned again.
        $configIds = DB::table('member_access_configs')
            ->join('members', 'members.id', '=', 'member_access_configs.member_id')
            ->whereNotNull('members.deleted_at')
            ->pluck('member_access_configs.id');

        foreach ($configIds->chunk(500) as $chunk) {
            DB::table('member_nfc_tags')
                ->whereIn('member_access_config_id', $chunk)
                ->delete();

            DB::table('member_access_configs')
                ->whereIn('id', $chunk)
                ->update([
                    'nfc_uid' => null,
                    'nfc_enabled' => false,
                    'nfc_registered_at' => null,
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Released tags may already belong to other members; they are not
        // restored.
    }
};
