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
        Schema::create('scanner_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained();
            $table->string('device_number', 10);
            $table->string('member_id');
            $table->enum('scan_type', ['qr_code', 'nfc_card']);
            $table->boolean('access_granted');
            $table->string('denial_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['gym_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scanner_access_logs');
    }
};
