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
        Schema::table('member_access_configs', function (Blueprint $table) {
            // Corporate fitness aggregator (EGYM Wellpass, Hansefit, ...) the
            // member checks in through, and the member's account id there.
            $table->string('aggregator', 32)->nullable()->after('additional_services');
            $table->string('aggregator_account_id', 100)->nullable()->after('aggregator');
            // Set once a check-in has confirmed the account. Null while pending.
            $table->timestamp('aggregator_linked_at')->nullable()->after('aggregator_account_id');

            $table->index(['aggregator', 'aggregator_account_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_access_configs', function (Blueprint $table) {
            $table->dropIndex(['aggregator', 'aggregator_account_id']);
            $table->dropColumn(['aggregator', 'aggregator_account_id', 'aggregator_linked_at']);
        });
    }
};
