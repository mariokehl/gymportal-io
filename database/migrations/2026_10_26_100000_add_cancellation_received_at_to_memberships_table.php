<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When a cancellation was received. § 312k (4) BGB requires the
     * confirmation to state this moment, which cancellation_date (the day the
     * membership ends) cannot provide. Older cancellations stay null.
     */
    public function up(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->timestamp('cancellation_received_at')->nullable()->after('cancellation_reason');
        });
    }

    public function down(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->dropColumn('cancellation_received_at');
        });
    }
};
