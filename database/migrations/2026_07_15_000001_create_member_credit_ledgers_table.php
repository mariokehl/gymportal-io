<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_credit_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained('gyms')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            // The payment that triggered this entry (top-up or redemption), if any.
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('type', ['topup', 'redemption', 'refund', 'adjustment']);

            // Amounts are stored as encrypted integer cents (Laravel encrypt()); never as plaintext.
            $table->text('encrypted_amount');
            $table->text('encrypted_balance_after');

            // Tamper-evident HMAC chain: each row signs its own values plus the
            // previous row's hash, so any altered/inserted/deleted/reordered row
            // breaks the chain and is detected during verification.
            $table->char('entry_hash', 64);
            $table->char('prev_hash', 64)->nullable();

            $table->string('description');
            $table->timestamps();

            // Fast, ordered chain reconstruction per member.
            $table->index(['member_id', 'id']);
            $table->index(['gym_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_credit_ledgers');
    }
};
