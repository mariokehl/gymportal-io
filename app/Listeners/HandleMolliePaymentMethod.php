<?php

namespace App\Listeners;

use App\Events\MemberRegistered;
use App\Services\MollieService;
use Exception;
use Illuminate\Support\Facades\Log;

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
            try {
                $this->mollieService->handleMolliePaymentMethod($event->member, $paymentMethod);
            } catch (Exception $e) {
                Log::error('Member payment method handling failed', [
                    'member_id' => $event->member->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
}
