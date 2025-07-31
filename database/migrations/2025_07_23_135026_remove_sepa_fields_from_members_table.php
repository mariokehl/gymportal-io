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
            $table->dropColumn([
                'iban',
                'account_holder',
                'sepa_mandate_accepted',
                'sepa_mandate_date'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('iban')->nullable()->after('country');
            $table->string('account_holder')->nullable()->after('iban');
            $table->boolean('sepa_mandate_accepted')->default(false)->after('account_holder');
            $table->timestamp('sepa_mandate_date')->nullable()->after('sepa_mandate_accepted');
        });
    }
};
