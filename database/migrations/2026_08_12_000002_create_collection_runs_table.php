<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            // Human readable run number, unique per gym (e.g. IL-2026-005).
            $table->string('run_number');
            $table->string('partner')->default('diagonal');
            $table->timestamp('handed_over_at')->nullable();
            $table->enum('status', ['draft', 'handed_over', 'in_progress', 'completed', 'cancelled'])
                ->default('draft');
            $table->unsignedInteger('member_count')->default(0);
            $table->decimal('principal_amount', 10, 2)->default(0);
            $table->decimal('dunning_amount', 10, 2)->default(0);
            $table->decimal('flat_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['gym_id', 'run_number']);
            $table->index(['gym_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_runs');
    }
};
