<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('login_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->onDelete('cascade');
            $table->string('code', 6)->index(); // 6-stelliger Code
            $table->timestamp('expires_at')->index();
            $table->boolean('used')->default(false)->index();
            $table->timestamp('used_at')->nullable();
            $table->string('ip_address', 45)->nullable(); // IPv6 support
            $table->text('user_agent')->nullable();
            $table->timestamps();

            // Indexes für Performance
            $table->index(['member_id', 'code', 'used']);
            $table->index(['expires_at', 'used']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('login_codes');
    }
};
