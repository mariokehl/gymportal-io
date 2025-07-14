<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('registration_source')->default('manual')->after('notes');
            $table->json('widget_data')->nullable()->after('registration_source');
            $table->string('fitness_goals')->nullable()->after('widget_data');
        });
    }

    public function down()
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['registration_source', 'widget_data', 'fitness_goals']);
        });
    }
};
