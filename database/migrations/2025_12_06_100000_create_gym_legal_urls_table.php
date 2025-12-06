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
        Schema::create('gym_legal_urls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->onDelete('cascade');
            $table->enum('type', [
                'terms_and_conditions',      // Allgemeine Geschäftsbedingungen
                'cancellation_policy',       // Widerrufsbelehrung
                'privacy_policy',            // Datenschutzerklärung
                'terms_of_use',              // Nutzungsbedingungen
                'pricing',                   // Tarife
                'contract_conclusion',       // Vertragsabschluss
            ]);
            $table->string('url', 2048);
            $table->timestamps();

            // Jeder Typ darf pro Gym nur einmal existieren
            $table->unique(['gym_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gym_legal_urls');
    }
};
