<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('current_gym_id')->nullable()->constrained('gyms')->onDelete('set null');
            $table->index(['current_gym_id']);
        });

        $this->updateExistingUsers();
    }

    private function updateExistingUsers()
    {
        DB::table('users')->whereIn('email', ['max@fitzone.de', 'lisa@fitzone.de', 'thomas@fitzone.de'])->update(['current_gym_id' => 1]);
        DB::table('users')->where('email', 'anna@powerfit.de')->update(['current_gym_id' => 2]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['current_gym_id']);
            $table->dropIndex(['current_gym_id']);
            $table->dropColumn('current_gym_id');
        });
    }
};
