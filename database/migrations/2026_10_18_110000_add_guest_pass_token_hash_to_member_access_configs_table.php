<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hash of the token in the guest's wallet pass. Only the hash is stored, so
 * a database leak does not yield working passes; clearing it revokes the pass.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_access_configs', function (Blueprint $table) {
            $table->string('guest_pass_token_hash', 64)->nullable()->after('visit_card_entries');
        });
    }

    public function down(): void
    {
        Schema::table('member_access_configs', function (Blueprint $table) {
            $table->dropColumn('guest_pass_token_hash');
        });
    }
};
