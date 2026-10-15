<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addon_membership', function (Blueprint $table) {
            // Set when the add-on was cancelled because its contract was
            // cancelled, so revoking the contract cancellation can restore it.
            $table->boolean('cancelled_with_membership')->default(false)->after('cancelled_by');

            // An add-on cancelled on its own before the contract keeps its own
            // date here while the contract cancellation shortens it.
            $table->date('original_cancellation_effective_at')->nullable()->after('cancelled_with_membership');
        });
    }

    public function down(): void
    {
        Schema::table('addon_membership', function (Blueprint $table) {
            $table->dropColumn(['cancelled_with_membership', 'original_cancellation_effective_at']);
        });
    }
};
