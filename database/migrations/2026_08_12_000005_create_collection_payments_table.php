<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('collection_case_id')->constrained()->cascadeOnDelete();
            $table->date('booked_at');
            $table->decimal('amount', 10, 2);
            $table->enum('allocation_mode', ['auto', 'manual'])->default('auto');
            $table->string('source')->nullable();
            // Resolved allocation as claim id => amount.
            $table->json('allocation')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('diagonal_guid')->nullable();
            $table->string('diagonal_state')->nullable();
            $table->timestamps();

            $table->index('gym_id');
            $table->index('collection_case_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_payments');
    }
};
