<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Additional NFC tags of a member. The primary tag stays in
        // member_access_configs.nfc_uid; this table only holds the extra ones
        // some members carry for historical reasons.
        Schema::create('member_nfc_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_access_config_id')
                ->constrained('member_access_configs')
                ->cascadeOnDelete();
            $table->string('uid')->unique();
            $table->timestamp('registered_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_nfc_tags');
    }
};
