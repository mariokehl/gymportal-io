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
            $table->string('static_login_code', 6)->nullable()->after('member_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_access_configs', function (Blueprint $table) {
            $table->dropColumn('static_login_code');
        });
    }
};
