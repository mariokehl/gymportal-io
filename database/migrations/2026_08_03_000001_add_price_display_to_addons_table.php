<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addons', function (Blueprint $table) {
            // Recurring add-ons only: how the price is presented while choosing
            // in the widget. 'monthly' shows the monthly price, 'weekly' shows
            // the computed weekly equivalent as a comparison figure. Billing is
            // always monthly regardless of this setting.
            $table->string('price_display')->default('monthly')->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('addons', function (Blueprint $table) {
            $table->dropColumn('price_display');
        });
    }
};
