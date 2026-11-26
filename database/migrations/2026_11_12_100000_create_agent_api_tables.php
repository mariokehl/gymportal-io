<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agent API: tokens for external automations (AI agents, integrations) and an
 * audit trail of what they did.
 *
 * A token belongs to exactly one gym. The tenant always comes from the token,
 * never from the request.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gym_api_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // Why this client exists; needed for the record of processing activities.
            $table->string('purpose');
            // sha256 of the token. The plaintext is shown once on creation.
            $table->string('token_hash', 64)->unique();
            // First characters of the token, to tell clients apart in lists.
            $table->string('token_prefix', 12);
            $table->json('abilities');
            $table->dateTime('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_ip', 45)->nullable();
            $table->timestamps();

            $table->index(['gym_id', 'revoked_at']);
        });

        Schema::create('agent_api_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            // Deleting a client keeps what it did.
            $table->foreignId('gym_api_client_id')->nullable()->constrained('gym_api_clients')->nullOnDelete();
            $table->string('method', 8);
            $table->string('path');
            $table->string('ability', 40)->nullable();
            $table->unsignedBigInteger('member_id')->nullable();
            $table->unsignedSmallInteger('status');
            // true only when a write was actually executed (confirm=true and success)
            $table->boolean('changed_data')->default(false);
            $table->string('summary')->nullable();
            // Old and new values of an executed write, so every change can be traced and undone.
            $table->json('changes')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['gym_id', 'created_at']);
            $table->index(['gym_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_api_logs');
        Schema::dropIfExists('gym_api_clients');
    }
};
