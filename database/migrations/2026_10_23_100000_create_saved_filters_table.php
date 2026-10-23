<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Named filters of the filter builder, shared by the staff of a gym.
     * "context" names the list a filter belongs to (e.g. members), so other
     * lists can store their filters here as well.
     */
    public function up(): void
    {
        Schema::create('saved_filters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('context', 50);
            $table->string('name', 100);
            $table->json('definition');
            $table->timestamps();

            $table->index(['gym_id', 'context']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_filters');
    }
};
