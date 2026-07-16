<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Marks a payment as a manual credit top-up while keeping its real
            // payment method (e.g. banktransfer) intact.
            $table->boolean('is_credit_topup')->default(false)->after('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('is_credit_topup');
        });
    }
};
