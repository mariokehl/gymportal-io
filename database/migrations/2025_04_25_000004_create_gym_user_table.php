<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('gym_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('role')->default('staff'); // staff, trainer, etc.
            $table->timestamps();

            $table->unique(['gym_id', 'user_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('gym_user');
    }
};
