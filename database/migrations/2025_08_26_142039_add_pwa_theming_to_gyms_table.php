<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('gyms', function (Blueprint $table) {
            // PWA-spezifische Theming-Felder
            $table->string('primary_color', 7)->default('#e11d48')->after('logo_path'); // Matching existing widget primary
            $table->string('secondary_color', 7)->default('#64748b')->after('primary_color');
            $table->string('accent_color', 7)->default('#10b981')->after('secondary_color');
            $table->string('background_color', 7)->nullable()->after('accent_color');
            $table->string('text_color', 7)->nullable()->after('background_color');

            // PWA Assets
            $table->string('pwa_logo_url')->nullable()->after('text_color'); // Separate PWA logo
            $table->string('favicon_url')->nullable()->after('pwa_logo_url');

            // Custom Styling
            $table->text('custom_css')->nullable()->after('favicon_url');

            // PWA Settings
            $table->boolean('pwa_enabled')->default(true)->after('custom_css');
            $table->json('pwa_settings')->nullable()->after('pwa_enabled');

            // Extended Gym Info für PWA
            $table->json('opening_hours')->nullable()->after('pwa_settings');
            $table->json('social_media')->nullable()->after('opening_hours');
            $table->text('member_app_description')->nullable()->after('social_media'); // Separate description for member app
        });
    }

    public function down()
    {
        Schema::table('gyms', function (Blueprint $table) {
            $table->dropColumn([
                'primary_color', 'secondary_color', 'accent_color',
                'background_color', 'text_color', 'pwa_logo_url',
                'favicon_url', 'custom_css', 'pwa_enabled',
                'pwa_settings', 'opening_hours', 'social_media',
                'member_app_description'
            ]);
        });
    }
};
