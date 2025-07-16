<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('check_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained();
            $table->foreignId('gym_id')->constrained();
            $table->timestamp('check_in_time');
            $table->timestamp('check_out_time')->nullable();
            $table->string('check_in_method')->default('qr_code'); // qr_code, manual, etc.
            $table->foreignId('checked_in_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('check_ins');
    }
};
