<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('gym_id')->after('id')->nullable()->constrained()->onDelete('cascade');
            $table->string('currency', 3)->after('amount')->default('EUR');
            $table->string('mollie_status')->after('status')->nullable();
            $table->text('checkout_url')->after('mollie_status')->nullable();
            $table->foreignId('user_id')->after('checkout_url')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('member_id')->after('user_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('invoice_id')->after('member_id')->nullable()->constrained('invoices')->onDelete('set null');
            $table->json('metadata')->after('invoice_id')->nullable();
            $table->timestamp('paid_at')->after('metadata')->nullable();
            $table->timestamp('failed_at')->after('paid_at')->nullable();
            $table->timestamp('canceled_at')->after('failed_at')->nullable();
            $table->timestamp('expired_at')->after('canceled_at')->nullable();
            $table->timestamp('webhook_processed_at')->after('expired_at')->nullable();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->enum('status', ['pending', 'paid', 'failed', 'refunded', 'completed', 'canceled', 'expired', 'unknown'])->change();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->unique('mollie_payment_id');
        });

        $this->updateExistingPayments();

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('gym_id')->nullable(false)->change();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index(['gym_id', 'status']);
            $table->index(['gym_id', 'created_at']);
            $table->index('mollie_payment_id');
        });
    }


    /**
     * Update existing payments with gym_id based on membership relationship
     */
    private function updateExistingPayments()
    {
        DB::statement('
            UPDATE payments p
            INNER JOIN memberships m ON p.membership_id = m.id
            INNER JOIN members mem ON m.member_id = mem.id
            SET p.gym_id = mem.gym_id
            WHERE p.gym_id IS NULL
        ');
    }

    public function down()
    {
        Schema::table('payments', function (Blueprint $table) {
            // 1. Zuerst alle Foreign Key Constraints entfernen
            $table->dropForeign(['gym_id']);
            $table->dropForeign(['user_id']); // falls vorhanden
            $table->dropForeign(['member_id']); // falls vorhanden
            $table->dropForeign(['invoice_id']); // falls vorhanden

            // 2. Dann die Indizes entfernen
            $table->dropIndex(['gym_id', 'status']);
            $table->dropIndex(['gym_id', 'created_at']);
            $table->dropIndex(['mollie_payment_id']);
            $table->dropUnique(['mollie_payment_id']);

            // 3. Spaltentyp ändern
            $table->enum('status', ['pending', 'paid', 'failed', 'refunded'])->change();

            // 4. Spalten löschen
            $table->dropColumn([
                'gym_id',
                'currency',
                'mollie_status',
                'checkout_url',
                'user_id',
                'member_id',
                'invoice_id',
                'metadata',
                'paid_at',
                'failed_at',
                'canceled_at',
                'expired_at',
                'webhook_processed_at'
            ]);
        });
    }
};
