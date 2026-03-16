<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_blocklist', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained('gyms')->cascadeOnDelete();
            $table->foreignId('original_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->foreignId('blocked_by')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('reason', ['payment_failed', 'chargeback', 'fraud', 'manual']);
            $table->text('notes')->nullable();

            // Gehashte Identifier (SHA-256 + App-Salt, kein Plaintext)
            $table->string('hash_iban', 64)->nullable()->index();
            $table->string('hash_phone', 64)->nullable()->index();
            $table->string('hash_address', 64)->nullable()->index();

            // Fuzzy-Matching: Klarnamen für Levenshtein (verschlüsselt gespeichert via Laravel encrypt())
            $table->text('encrypted_last_name')->nullable();
            $table->text('encrypted_first_name')->nullable();
            $table->text('encrypted_birthdate')->nullable();

            $table->timestamp('blocked_at');
            $table->timestamp('blocked_until')->nullable(); // NULL = permanent
            $table->timestamps();

            $table->index(['gym_id', 'hash_iban']);
            $table->index(['gym_id', 'hash_phone']);
            $table->index(['gym_id', 'blocked_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_blocklist');
    }
};
