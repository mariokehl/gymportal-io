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
        Schema::create('member_access_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->unique()->constrained()->onDelete('cascade');

            // Primary access methods
            $table->boolean('qr_code_enabled')->default(true);
            $table->timestamp('qr_code_invalidated_at')->nullable();
            $table->foreignId('qr_code_invalidated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->boolean('nfc_enabled')->default(false);
            $table->string('nfc_uid')->nullable()->unique()->index();
            $table->timestamp('nfc_registered_at')->nullable();

            // Additional services
            $table->boolean('solarium_enabled')->default(false);
            $table->integer('solarium_minutes')->default(0);

            $table->boolean('vending_enabled')->default(false);
            $table->decimal('vending_credit', 10, 2)->default(0);

            $table->boolean('massage_enabled')->default(false);
            $table->integer('massage_sessions')->default(0);

            $table->boolean('coffee_flat_enabled')->default(false);
            $table->date('coffee_flat_expiry')->nullable();

            // Additional fields for future services
            $table->json('additional_services')->nullable();

            $table->timestamps();

            // Indexes for performance
            $table->index('qr_code_enabled');
            $table->index('nfc_enabled');
            $table->index(['member_id', 'qr_code_enabled']);
            $table->index(['nfc_uid', 'nfc_enabled']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_access_configs');
    }
};
