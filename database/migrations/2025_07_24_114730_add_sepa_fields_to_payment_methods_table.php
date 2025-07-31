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
        Schema::table('payment_methods', function (Blueprint $table) {
            // SEPA-spezifische Felder
            $table->boolean('sepa_mandate_acknowledged')->default(false)->after('status');
            $table->enum('sepa_mandate_status', ['pending', 'signed', 'active', 'revoked', 'expired'])
                  ->nullable()
                  ->after('sepa_mandate_acknowledged');
            $table->timestamp('sepa_mandate_signed_at')->nullable()->after('sepa_mandate_status');
            $table->string('sepa_mandate_reference', 50)->nullable()->after('sepa_mandate_signed_at');
            $table->string('sepa_creditor_identifier', 50)->nullable()->after('sepa_mandate_reference');
            $table->json('sepa_mandate_data')->nullable()->after('sepa_creditor_identifier');

            // Index für SEPA-Mandate
            $table->index(['sepa_mandate_status', 'member_id']);
            $table->index('sepa_mandate_reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropIndex(['sepa_mandate_status', 'member_id']);
            $table->dropIndex(['sepa_mandate_reference']);

            $table->dropColumn([
                'sepa_mandate_acknowledged',
                'sepa_mandate_status',
                'sepa_mandate_signed_at',
                'sepa_mandate_reference',
                'sepa_creditor_identifier',
                'sepa_mandate_data'
            ]);
        });
    }
};
