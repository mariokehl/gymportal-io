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
        Schema::create('guest_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            // solarium_minutes, visit_card or day_pass (see GuestProduct::TYPES)
            $table->string('type', 30);
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 8, 2);
            // Solarium minutes or visit card entries granted per purchase; 1 for a day pass
            $table->unsignedInteger('quantity');
            $table->integer('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['gym_id', 'type', 'active']);
            $table->index(['gym_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guest_products');
    }
};
