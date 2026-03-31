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
        Schema::create('guest_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->onDelete('cascade');
            $table->foreignId('guest_product_id')->constrained()->onDelete('cascade');
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['pending', 'paid', 'expired', 'cancelled', 'consumed'])->default('pending');
            $table->integer('credits_remaining')->nullable();
            $table->dateTime('valid_until')->nullable();
            $table->dateTime('activated_at')->nullable();
            $table->timestamps();

            $table->index(['member_id', 'status']);
            $table->index(['guest_product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guest_purchases');
    }
};
