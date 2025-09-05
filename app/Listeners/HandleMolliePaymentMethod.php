<?php

namespace App\Listeners;

use App\Events\MemberRegistered;
use App\Services\MollieService;

class HandleMolliePaymentMethod
{
    /**
     * Create the event listener.
     */
    public function __construct(
        private MollieService $mollieService
    ) {}

    /**
     * Handle the event.
     */
    public function handle(MemberRegistered $event): void
    {
        $paymentMethod = $event->additionalData['payment_method'] ?? null;

        if ($paymentMethod?->type === 'mollie_directdebit') {
            $this->mollieService->handleMolliePaymentMethod($event->member, $paymentMethod);
        }
    }
}
