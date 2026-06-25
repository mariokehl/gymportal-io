<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addon_membership_plan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('addon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_plan_id')->constrained()->cascadeOnDelete();
            // 'included' = preselected and not deselectable, 'optional' = bookable.
            $table->string('mode')->default('optional');
            $table->timestamps();

            $table->unique(['addon_id', 'membership_plan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addon_membership_plan');
    }
};
