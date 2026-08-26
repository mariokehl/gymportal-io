<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The organisation symbol shown in the sidebar and the organization
     * switcher. Both columns are nullable so existing organisations keep
     * rendering the initial of their name on the default indigo tile.
     */
    public function up(): void
    {
        Schema::table('gyms', function (Blueprint $table) {
            $table->string('symbol_type', 16)->nullable()->after('display_name');
            $table->string('symbol_emoji', 16)->nullable()->after('symbol_type');
            $table->string('symbol_color', 7)->nullable()->after('symbol_emoji');
        });
    }

    public function down(): void
    {
        Schema::table('gyms', function (Blueprint $table) {
            $table->dropColumn(['symbol_type', 'symbol_emoji', 'symbol_color']);
        });
    }
};
