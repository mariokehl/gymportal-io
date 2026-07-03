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
        Schema::create('gym_google_sheet_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('google_sheet_enabled')->default(false);
            // Encrypted service account JSON key (Laravel encrypted cast)
            $table->text('credentials')->nullable();
            // Service account email extracted from the key, shown in the UI (not a secret)
            $table->string('service_account_email')->nullable();
            $table->string('spreadsheet_id')->nullable();
            $table->string('sheet_url')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gym_google_sheet_integrations');
    }
};
