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
            $table->boolean('day_pass_enabled')->default(false)->after('coffee_flat_expiry');
            $table->date('day_pass_valid_until')->nullable()->after('day_pass_enabled');
            $table->boolean('visit_card_enabled')->default(false)->after('day_pass_valid_until');
            $table->integer('visit_card_entries')->default(0)->after('visit_card_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_access_configs', function (Blueprint $table) {
            $table->dropColumn([
                'day_pass_enabled',
                'day_pass_valid_until',
                'visit_card_enabled',
                'visit_card_entries',
            ]);
        });
    }
};
