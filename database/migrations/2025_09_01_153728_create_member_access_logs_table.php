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
        Schema::create('member_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->onDelete('cascade');

            // Action details
            $table->string('action')->index(); // access_attempt, config_updated, qr_invalidated, etc.
            $table->string('service')->nullable(); // gym, solarium, vending, massage, coffee
            $table->string('method')->nullable(); // qr, nfc, manual
            $table->boolean('success')->default(false);

            // Who performed the action (if applicable)
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();

            // Device information
            $table->string('device_id')->nullable()->index();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();

            // Additional metadata
            $table->json('metadata')->nullable();

            $table->timestamp('accessed_at')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['member_id', 'action']);
            $table->index(['member_id', 'created_at']);
            $table->index(['service', 'success']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_access_logs');
    }
};
