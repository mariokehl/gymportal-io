<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Pending Solarium Redemptions: Wenn ein Gast in der PWA auf "Einlösen" klickt,
     * wird hier ein Eintrag erstellt. Der Pi am Solarium-POI pollt diese Tabelle,
     * triggert bei gefundenen Einträgen das Shelly Relay und bestätigt die Aktion
     * über den acknowledge-Endpoint.
     *
     * Pending Redemptions laufen nach 60 Sekunden automatisch ab und das
     * reservierte Guthaben wird zurückgebucht.
     */
    public function up(): void
    {
        Schema::create('pending_solarium_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('minutes');
            $table->enum('status', ['pending', 'completed', 'failed', 'expired', 'cancelled'])
                ->default('pending');
            $table->string('failure_reason')->nullable();
            $table->foreignId('acknowledged_by_scanner_id')->nullable()
                ->constrained('gym_scanners')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            // Index für Polling-Query: active redemptions pro Gym
            $table->index(['gym_id', 'status', 'created_at']);
            // Index für Expiry-Cleanup
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_solarium_redemptions');
    }
};
