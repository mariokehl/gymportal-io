<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Optional recommended retail price (UVP). When set and higher than the
     * actual price, the widget shows it struck through next to a computed
     * discount badge.
     */
    public function up(): void
    {
        Schema::table('membership_plans', function (Blueprint $table) {
            $table->decimal('original_price', 8, 2)
                ->nullable()
                ->after('price')
                ->comment('Optional UVP/list price shown struck through in the widget when higher than price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('membership_plans', function (Blueprint $table) {
            $table->dropColumn('original_price');
        });
    }
};
