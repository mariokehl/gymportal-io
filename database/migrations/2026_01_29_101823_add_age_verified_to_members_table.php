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
            $table->boolean('age_verified')->default(false)->after('birth_date');
            $table->timestamp('age_verified_at')->nullable()->after('age_verified');
            $table->foreignId('age_verified_by')->nullable()->after('age_verified_at')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropForeign(['age_verified_by']);
            $table->dropColumn(['age_verified', 'age_verified_at', 'age_verified_by']);
        });
    }
};
