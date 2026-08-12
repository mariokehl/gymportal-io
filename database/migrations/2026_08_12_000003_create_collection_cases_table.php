<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            // Null when the member was handed over individually instead of within a run.
            $table->foreignId('collection_run_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            // Internal case number, unique per gym (e.g. CASE-2026-0142).
            $table->string('case_number');
            // Reference provided by the collection partner, editable by the gym.
            $table->string('partner_reference')->nullable();
            $table->enum('status', ['in_progress', 'partial_payment', 'completed', 'cancelled', 'rejected'])
                ->default('in_progress');
            $table->timestamp('handed_over_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->decimal('principal_amount', 10, 2)->default(0);
            $table->decimal('dunning_amount', 10, 2)->default(0);
            $table->decimal('flat_amount', 10, 2)->default(0);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->text('notes')->nullable();
            // The DIAGONAL API is asynchronous: writes return a GUID whose processing
            // state has to be polled separately via GetStateByGuid.
            $table->string('diagonal_guid')->nullable();
            $table->string('diagonal_state')->nullable();
            $table->timestamp('diagonal_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['gym_id', 'case_number']);
            $table->index(['gym_id', 'status']);
            $table->index('member_id');
            $table->index('diagonal_guid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_cases');
    }
};
