<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('membership_plans', function (Blueprint $table) {
            $table->decimal('setup_fee', 8, 2)->default(0)->after('price');
            $table->integer('trial_period_days')->default(0)->after('setup_fee');
            $table->decimal('trial_price', 8, 2)->default(0)->after('trial_period_days');
            $table->json('features')->nullable()->after('cancellation_period_days');
            $table->json('widget_display_options')->nullable()->after('features');
            $table->integer('sort_order')->default(0)->after('widget_display_options');
            $table->boolean('highlight')->default(false)->after('sort_order');
            $table->string('badge_text')->nullable()->after('highlight');
        });
    }

    public function down()
    {
        Schema::table('membership_plans', function (Blueprint $table) {
            $table->dropColumn([
                'setup_fee',
                'trial_period_days',
                'trial_price',
                'features',
                'widget_display_options',
                'sort_order',
                'highlight',
                'badge_text'
            ]);
        });
    }
};
