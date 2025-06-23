<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::rename('gym_user', 'gym_users');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('gym_users', 'gym_user');
    }
};
