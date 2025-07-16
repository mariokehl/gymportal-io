<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('widget_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->onDelete('cascade');
            $table->string('event_type'); // 'view', 'plan_selected', 'form_started', 'form_completed', 'registration_completed'
            $table->string('step')->nullable(); // 'plans', 'form', 'checkout'
            $table->json('data')->nullable();
            $table->string('session_id')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('referrer')->nullable();
            $table->timestamp('created_at');

            $table->index(['gym_id', 'event_type']);
            $table->index(['gym_id', 'created_at']);
            $table->index(['session_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('widget_analytics');
    }
};
