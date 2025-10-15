<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration replaces the global unique constraint on member_number
     * with a composite unique constraint on (gym_id, member_number),
     * allowing the same member number to exist across different gyms.
     */
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            // Drop the existing unique index on member_number
            $table->dropUnique(['member_number']);

            // Add composite unique index on gym_id and member_number
            $table->unique(['gym_id', 'member_number'], 'members_gym_id_member_number_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            // Drop the composite unique index
            $table->dropUnique('members_gym_id_member_number_unique');

            // Restore the original unique index on member_number only
            $table->unique('member_number');
        });
    }
};
