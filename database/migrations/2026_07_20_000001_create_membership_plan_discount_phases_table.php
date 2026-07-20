<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_plan_discount_phases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_plan_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedSmallInteger('duration_months');
            // Absolute override price for the phase, not a percentage off.
            $table->decimal('price', 8, 2);
            $table->decimal('original_price', 8, 2)->nullable();
            $table->timestamps();

            $table->index(['membership_plan_id', 'sort_order']);
        });

        Schema::table('membership_plans', function (Blueprint $table) {
            $table->boolean('discounts_enabled')->default(false)->after('original_price');
        });
    }

    public function down(): void
    {
        Schema::table('membership_plans', function (Blueprint $table) {
            $table->dropColumn('discounts_enabled');
        });

        Schema::dropIfExists('membership_plan_discount_phases');
    }
};
