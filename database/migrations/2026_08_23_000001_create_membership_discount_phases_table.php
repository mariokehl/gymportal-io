<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-membership copy of the plan's discount phases, taken when the
     * contract is signed.
     *
     * Billing reads these rows, never membership_plan_discount_phases, so a
     * later change to the plan's discount ladder only affects contracts signed
     * after it. A membership without rows here is charged the regular plan
     * price, which is how every contract signed before this feature behaves.
     */
    public function up(): void
    {
        Schema::create('membership_discount_phases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedSmallInteger('duration_months');
            // Absolute override price for the phase, not a percentage off.
            $table->decimal('price', 8, 2);
            $table->decimal('original_price', 8, 2)->nullable();
            $table->timestamps();

            $table->index(['membership_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_discount_phases');
    }
};
