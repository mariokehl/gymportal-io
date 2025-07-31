<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Gym;
use App\Models\PaymentMethod;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('gyms', function (Blueprint $table) {
            $table->text('payment_methods_config')->nullable()->after('mollie_config');
        });

        // Initialisiere bestehende Gyms mit Standard-Konfiguration
        $this->seedExistingGyms();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gyms', function (Blueprint $table) {
            $table->dropColumn('payment_methods_config');
        });
    }

    /**
     * Bestehende Gyms mit Standard Payment Methods Konfiguration initialisieren
     */
    private function seedExistingGyms(): void
    {
        // Alle bestehenden Gyms ohne payment_methods_config aktualisieren
        Gym::whereNull('payment_methods_config')->update([
            'payment_methods_config' => json_encode(PaymentMethod::getDefaultConfig())
        ]);
    }
};
