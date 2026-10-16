<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A guest tapping "Einlösen" in the guest app creates a pending row here.
     * The solarium scanner polls this table, switches the relay and confirms
     * via the acknowledge endpoint.
     *
     * Pending rows expire after PendingSolariumRedemption::EXPIRY_SECONDS and
     * the reserved minutes are returned to the guest.
     */
    public function up(): void
    {
        Schema::create('pending_solarium_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('minutes');
            // pending, completed, failed, expired or cancelled
            $table->string('status', 20)->default('pending');
            $table->string('failure_reason')->nullable();
            $table->foreignId('acknowledged_by_scanner_id')->nullable()
                ->constrained('gym_scanners')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            // Polling query: open redemptions per gym
            $table->index(['gym_id', 'status', 'created_at']);
            // Expiry cleanup
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_solarium_redemptions');
    }
};
