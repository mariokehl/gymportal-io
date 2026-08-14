<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * New devices only ever store the hash, so the plaintext column has to
     * accept NULL. It stays UNIQUE — MariaDB and SQLite both allow repeated
     * NULLs in a unique index, so hash-only devices do not collide.
     */
    public function up(): void
    {
        Schema::table('gym_scanners', function (Blueprint $table) {
            $table->string('api_token', 80)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * Devices provisioned after the hash change have no plaintext to restore,
     * so the column cannot simply be made NOT NULL again without locking them
     * out. Their tokens have to be regenerated first.
     */
    public function down(): void
    {
        Schema::table('gym_scanners', function (Blueprint $table) {
            $table->string('api_token', 80)->nullable(false)->change();
        });
    }
};
