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
        Schema::table('gyms', function (Blueprint $table) {
            $table->string('iban', 34)->nullable()->after('email');
            $table->string('bic', 11)->nullable()->after('iban');
            $table->string('creditor_identifier', 35)->nullable()->after('bic');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gyms', function (Blueprint $table) {
            $table->dropColumn(['iban', 'bic', 'creditor_identifier']);
        });
    }
};
