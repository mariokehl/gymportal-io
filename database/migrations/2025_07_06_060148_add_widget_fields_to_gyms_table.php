<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('gyms', function (Blueprint $table) {
            $table->string('api_key')->unique()->nullable()->after('email');
            $table->boolean('widget_enabled')->default(false)->after('api_key');
            $table->json('widget_settings')->nullable()->after('widget_enabled');
            $table->timestamp('api_key_generated_at')->nullable()->after('widget_settings');
        });
    }

    public function down()
    {
        Schema::table('gyms', function (Blueprint $table) {
            $table->dropColumn(['api_key', 'widget_enabled', 'widget_settings', 'api_key_generated_at']);
        });
    }
};
