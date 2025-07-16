<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('course_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_schedule_id')->constrained();
            $table->foreignId('member_id')->constrained();
            $table->enum('status', ['booked', 'attended', 'no_show', 'cancelled'])->default('booked');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['course_schedule_id', 'member_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('course_bookings');
    }
};
