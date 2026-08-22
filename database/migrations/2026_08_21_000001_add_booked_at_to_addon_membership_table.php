<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The booking date used to be read from created_at, which is wrong for a
     * contract taken over from another system: there the add-on was booked
     * long before the import row was written.
     */
    public function up(): void
    {
        Schema::table('addon_membership', function (Blueprint $table) {
            $table->date('booked_at')->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('addon_membership', function (Blueprint $table) {
            $table->dropColumn('booked_at');
        });
    }
};
