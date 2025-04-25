<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('capacity');
            $table->integer('duration_minutes');
            $table->boolean('requires_booking')->default(true);
            $table->foreignId('instructor_id')->nullable()->constrained('users');
            $table->string('color')->default('#3490dc');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('courses');
    }
};
