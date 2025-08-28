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
        Schema::create('gym_scanners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->onDelete('cascade');
            $table->string('device_number', 10);
            $table->string('device_name')->nullable();
            $table->string('api_token', 80)->unique(); // Eindeutiges API Token
            $table->string('ip_address', 45)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('token_expires_at')->nullable(); // Optional: Token-Ablauf
            $table->integer('failed_attempts')->default(0);
            $table->timestamp('locked_until')->nullable(); // Brute-Force Schutz
            $table->json('allowed_ips')->nullable(); // IP-Whitelist (optional)
            $table->timestamps();

            $table->unique(['gym_id', 'device_number']);
            $table->index('api_token'); // Index für schnelle Lookups
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gym_scanners');
    }
};
