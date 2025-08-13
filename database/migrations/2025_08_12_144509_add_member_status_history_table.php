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
        // NUR die Status History Tabelle für detailliertes Tracking
        if (!Schema::hasTable('member_status_history')) {
            Schema::create('member_status_history', function (Blueprint $table) {
                $table->id();
                $table->foreignId('member_id')->constrained()->onDelete('cascade');
                $table->string('old_status', 50);
                $table->string('new_status', 50);
                $table->string('reason')->nullable();
                $table->foreignId('changed_by')->nullable()->constrained('users');
                $table->json('metadata')->nullable(); // Für zusätzliche Informationen
                $table->timestamps();

                // Indices für Performance
                $table->index(['member_id', 'created_at']);
                $table->index(['member_id', 'new_status']);
                $table->index('changed_by');
                $table->index('created_at'); // Für zeitbasierte Abfragen
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_status_history');
    }
};
