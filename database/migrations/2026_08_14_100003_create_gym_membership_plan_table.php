<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The locations a membership plan is valid at, used when its location_scope is
 * 'selected'. The plan's own gym is always implied and is not stored here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gym_membership_plan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gym_id')->constrained('gyms')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['membership_plan_id', 'gym_id']);
            $table->index('gym_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gym_membership_plan');
    }
};
