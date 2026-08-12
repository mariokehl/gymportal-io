<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('collection_case_id')->constrained()->cascadeOnDelete();
            // The originating payment, if the claim was created from one.
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->date('due_date')->nullable();
            $table->decimal('amount', 10, 2)->default(0);
            $table->decimal('paid_amount', 10, 2)->default(0);
            // Maps to the DIAGONAL payload lists: principal => invoiceList,
            // dunning => dunningList, flat => expensesList.
            $table->enum('kind', ['principal', 'dunning', 'flat'])->default('principal');
            $table->boolean('written_off')->default(false);
            $table->timestamps();

            $table->index('gym_id');
            $table->index(['collection_case_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_claims');
    }
};
