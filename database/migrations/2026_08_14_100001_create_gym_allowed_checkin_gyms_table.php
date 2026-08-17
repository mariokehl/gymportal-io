<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which other locations a gym accepts members from, used when its
 * cross_location_checkin_rule is 'selected'.
 *
 * gym_id is the location granting access, allowed_gym_id the home location of
 * the visiting member. The relation is deliberately directed — Berlin may let
 * Hamburg members in without Hamburg reciprocating.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gym_allowed_checkin_gyms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained('gyms')->cascadeOnDelete();
            $table->foreignId('allowed_gym_id')->constrained('gyms')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['gym_id', 'allowed_gym_id']);
            $table->index('allowed_gym_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gym_allowed_checkin_gyms');
    }
};
