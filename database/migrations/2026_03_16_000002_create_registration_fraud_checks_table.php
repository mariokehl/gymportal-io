<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_fraud_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained('gyms')->cascadeOnDelete();
            $table->foreignId('member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->foreignId('blocklist_entry_id')->nullable()->constrained('member_blocklist')->nullOnDelete();

            $table->string('email');
            $table->integer('fraud_score'); // 0–100
            $table->json('matched_fields'); // ['iban' => 100, 'phone' => 80]
            $table->enum('action', ['allowed', 'flagged', 'blocked']);
            $table->string('ip_address', 45)->nullable();

            $table->timestamp('checked_at');
            $table->timestamps();

            $table->index(['gym_id', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_fraud_checks');
    }
};
