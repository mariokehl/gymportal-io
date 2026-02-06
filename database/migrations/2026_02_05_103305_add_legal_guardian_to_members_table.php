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
        Schema::table('members', function (Blueprint $table) {
            // Gesetzlicher Vertreter - kann ein anderes Mitglied sein oder manuell eingegeben werden
            $table->foreignId('legal_guardian_member_id')
                ->nullable()
                ->after('emergency_contact_phone')
                ->constrained('members')
                ->nullOnDelete();
            $table->string('legal_guardian_first_name')->nullable()->after('legal_guardian_member_id');
            $table->string('legal_guardian_last_name')->nullable()->after('legal_guardian_first_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropForeign(['legal_guardian_member_id']);
            $table->dropColumn([
                'legal_guardian_member_id',
                'legal_guardian_first_name',
                'legal_guardian_last_name',
            ]);
        });
    }
};
