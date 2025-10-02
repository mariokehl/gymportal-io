<?php

namespace App\Events;

use App\Models\Member;
use App\Models\PaymentMethod;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MollieMandateCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Member $member,
        public PaymentMethod $paymentMethod,
        public string $mandateId
    ) {
    }
}
