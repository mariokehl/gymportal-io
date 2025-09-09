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
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('type'); // welcome, confirmation, reminder, cancellation, invoice, general
            $table->string('subject');
            $table->longText('body');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->json('variables')->nullable(); // Store available variables for this template
            $table->timestamps();

            // Ensure only one default template per type per gym
            $table->unique(['gym_id', 'type', 'is_default'], 'unique_default_template');
            $table->index(['gym_id', 'type']);
            $table->index(['gym_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
