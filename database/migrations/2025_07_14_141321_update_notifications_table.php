<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Erweitere die notifications Tabelle falls nötig
        Schema::table('notifications', function (Blueprint $table) {
            if (!Schema::hasColumn('notifications', 'link_type')) {
                $table->string('link_type')->nullable()->after('type');
            }
            if (!Schema::hasColumn('notifications', 'link_id')) {
                $table->unsignedBigInteger('link_id')->nullable()->after('link_type');
            }
            if (!Schema::hasColumn('notifications', 'metadata')) {
                $table->json('metadata')->nullable()->after('link_id');
            }
        });

        // Erweitere die notification_recipients Tabelle falls nötig
        Schema::table('notification_recipients', function (Blueprint $table) {
            if (!Schema::hasColumn('notification_recipients', 'clicked_at')) {
                $table->timestamp('clicked_at')->nullable()->after('read_at');
            }
        });
    }

    public function down()
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn(['link_type', 'link_id', 'metadata']);
        });

        Schema::table('notification_recipients', function (Blueprint $table) {
            $table->dropColumn('clicked_at');
        });
    }
};
