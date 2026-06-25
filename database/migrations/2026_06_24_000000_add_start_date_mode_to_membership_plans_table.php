<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_plans', function (Blueprint $table) {
            // 'next_possible' = current behaviour (start_date = now()),
            // 'fixed' = force start_date to fixed_start_date until that date is reached.
            $table->string('start_date_mode')->default('next_possible')->after('auto_renew_type');
            $table->date('fixed_start_date')->nullable()->after('start_date_mode');
        });
    }

    public function down(): void
    {
        Schema::table('membership_plans', function (Blueprint $table) {
            $table->dropColumn(['start_date_mode', 'fixed_start_date']);
        });
    }
};
