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
        Schema::create('chargebacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->onDelete('cascade');
            $table->string('mollie_chargeback_id')->unique();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('EUR');
            $table->enum('status', ['received', 'accepted', 'disputed', 'reversed'])->default('received');
            $table->string('mollie_status')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('chargeback_date')->nullable();
            $table->timestamps();

            $table->index('payment_id');
            $table->index('mollie_chargeback_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chargebacks');
    }
};
