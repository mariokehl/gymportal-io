<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addon_membership', function (Blueprint $table) {
            // When the member requested the cancellation of a recurring add-on.
            $table->timestamp('cancelled_at')->nullable()->after('completed_by');

            // Recurring add-ons are cancellable to the end of the month, so the
            // service stays usable until this date.
            $table->date('cancellation_effective_at')->nullable()->after('cancelled_at');

            $table->foreignId('cancelled_by')->nullable()->after('cancellation_effective_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('addon_membership', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['cancelled_at', 'cancellation_effective_at']);
        });
    }
};
