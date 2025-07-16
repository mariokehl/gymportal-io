<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->onDelete('cascade');
            $table->string('mollie_refund_id')->unique();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('EUR');
            $table->string('description')->nullable();
            $table->enum('status', ['pending', 'processing', 'refunded', 'failed'])->default('pending');
            $table->string('mollie_status')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('reason')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('payment_id');
            $table->index('mollie_refund_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('refunds');
    }
};
