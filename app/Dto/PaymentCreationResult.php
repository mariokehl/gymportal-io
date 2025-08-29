<?php

namespace App\Dto;

use App\Models\Payment;
use Illuminate\Support\Collection;

class PaymentCreationResult
{
    public function __construct(
        public readonly ?Payment $setupPayment = null,
        public readonly ?Payment $initialPayment = null,
        public readonly array $recurringPayments = []
    ) {}

    /**
     * Alle erstellten Payments als Collection
     */
    public function getAllPayments(): Collection
    {
        $payments = collect();

        if ($this->setupPayment) {
            $payments->push($this->setupPayment);
        }

        if ($this->initialPayment) {
            $payments->push($this->initialPayment);
        }

        $payments = $payments->merge($this->recurringPayments);

        return $payments;
    }

    /**
     * Anzahl aller erstellten Payments
     */
    public function getTotalCount(): int
    {
        return $this->getAllPayments()->count();
    }

    /**
     * Gesamtsumme aller Payments
     */
    public function getTotalAmount(): float
    {
        return $this->getAllPayments()->sum('amount');
    }

    /**
     * IDs aller erstellten Payments
     */
    public function getPaymentIds(): array
    {
        return $this->getAllPayments()->pluck('id')->toArray();
    }

    /**
     * Hat Setup-Payment?
     */
    public function hasSetupPayment(): bool
    {
        return $this->setupPayment !== null;
    }

    /**
     * Hat wiederkehrende Payments?
     */
    public function hasRecurringPayments(): bool
    {
        return count($this->recurringPayments) > 0;
    }

    /**
     * Für API Response
     */
    public function toArray(): array
    {
        return [
            'setup_payment' => $this->setupPayment?->only(['id', 'amount', 'due_date', 'status']),
            'initial_payment' => $this->initialPayment?->only(['id', 'amount', 'due_date', 'status']),
            'recurring_payments' => collect($this->recurringPayments)->map(fn($p) =>
                $p->only(['id', 'amount', 'due_date', 'status'])
            )->toArray(),
            'summary' => [
                'total_count' => $this->getTotalCount(),
                'total_amount' => $this->getTotalAmount(),
                'has_setup_fee' => $this->hasSetupPayment(),
                'recurring_count' => count($this->recurringPayments)
            ]
        ];
    }
}
