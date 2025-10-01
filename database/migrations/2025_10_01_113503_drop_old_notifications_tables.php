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
        Schema::dropIfExists('notification_recipients');
        Schema::dropIfExists('notifications');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate old notifications table structure
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->text('title');
            $table->text('message')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();
        });

        // Recreate old notification_recipients table structure
        Schema::create('notification_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }
};
