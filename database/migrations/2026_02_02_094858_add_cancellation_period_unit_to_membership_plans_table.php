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
        Schema::table('membership_plans', function (Blueprint $table) {
            // Rename cancellation_period_days to cancellation_period to be more generic
            $table->renameColumn('cancellation_period_days', 'cancellation_period');
        });

        Schema::table('membership_plans', function (Blueprint $table) {
            // Add unit column: 'days' or 'months'
            $table->string('cancellation_period_unit', 10)->default('days')->after('cancellation_period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('membership_plans', function (Blueprint $table) {
            $table->dropColumn('cancellation_period_unit');
        });

        Schema::table('membership_plans', function (Blueprint $table) {
            $table->renameColumn('cancellation_period', 'cancellation_period_days');
        });
    }
};
