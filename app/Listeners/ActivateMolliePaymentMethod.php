<?php

namespace App\Listeners;

use App\Events\MollieMandateCreated;
use App\Services\MemberService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class ActivateMolliePaymentMethod implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(MollieMandateCreated $event): void
    {
        $paymentMethod = $event->paymentMethod;
        $member = $event->member;

        // Update payment method with mandate and activate
        $paymentMethod->update([
            'mollie_mandate_id' => $event->mandateId,
            'iban' => '' // Clear IBAN for security
        ]);

        $paymentMethod->activateSepaMandate();

        // Activate member and membership
        $member->update(['status' => 'active']);

        $membership = $member->memberships->first();
        if ($membership) {
            if (!$membership->activateMembership()) {
                $membership->update(['status' => 'active']);
            }

            // Vertrag wurde durch MembershipActivated-Event generiert – Welcome-Mail jetzt mit Vertrag senden
            $membership->refresh();
            $gym = $member->gym;
            app(MemberService::class)->sendWelcomeEmail($member, $gym, $membership->contract_file_path);
        }

        Log::info('Payment method activated after mandate creation', [
            'member_id' => $member->id,
            'payment_method_id' => $paymentMethod->id,
            'mandate_id' => $event->mandateId,
        ]);
    }
}
