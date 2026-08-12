<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dunning_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            // The payment that triggered this dunning level, if any.
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            // 1 = payment reminder, 2 = first notice, 3 = second notice, 4 = handed over to collection
            $table->unsignedTinyInteger('level');
            $table->decimal('fee', 10, 2)->default(0);
            $table->timestamp('triggered_at');
            $table->timestamp('sent_at')->nullable();
            $table->string('channel')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('gym_id');
            $table->index(['member_id', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dunning_notices');
    }
};
