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
        Schema::table('members', function (Blueprint $table) {
            $table->boolean('guest_access')->default(false)->after('age_verified_by');
            $table->timestamp('guest_access_granted_at')->nullable()->after('guest_access');
            $table->foreignId('guest_access_granted_by')->nullable()->after('guest_access_granted_at')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropForeign(['guest_access_granted_by']);
            $table->dropColumn(['guest_access', 'guest_access_granted_at', 'guest_access_granted_by']);
        });
    }
};
