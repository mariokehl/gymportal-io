<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PaymentSeeder extends Seeder
{
    private array $chargebackReasons = [
        'Nicht autorisierte Transaktion',
        'Produkt/Dienstleistung nicht erhalten',
        'Doppelte Abbuchung',
        'Falscher Betrag abgebucht',
        'Mitgliedschaft bereits gekündigt',
        'Konto nicht gedeckt',
    ];

    private array $refundReasons = [
        'Kündigung vor Ablauf',
        'Kulanz-Erstattung',
        'Doppelzahlung',
        'Preisanpassung',
        'Servicebeschwerde',
        'Vertragswiderruf',
    ];

    public function run()
    {
        $paidPayments = [];

        // Get all memberships
        $memberships = DB::table('memberships')->get();

        foreach ($memberships as $membership) {
            $membershipPlan = DB::table('membership_plans')->where('id', $membership->membership_plan_id)->first();
            $startDate = Carbon::parse($membership->start_date);

            // Create payment history based on billing cycle
            $paymentCount = $membershipPlan->billing_cycle === 'monthly' ? 12 :
                          ($membershipPlan->billing_cycle === 'quarterly' ? 4 : 1);

            for ($i = 0; $i < $paymentCount; $i++) {
                if ($membershipPlan->billing_cycle === 'monthly') {
                    $dueDate = $startDate->copy()->addMonths($i);
                } elseif ($membershipPlan->billing_cycle === 'quarterly') {
                    $dueDate = $startDate->copy()->addMonths($i * 3);
                } else {
                    $dueDate = $startDate;
                }

                // Skip future payments
                if ($dueDate > Carbon::now()) {
                    continue;
                }

                $status = 'paid';
                $paidDate = $dueDate->copy();

                // For some members, create overdue payments
                if ($i === $paymentCount - 1 && rand(1, 10) === 1) {
                    $status = 'pending';
                    $paidDate = null;
                }

                $paymentId = DB::table('payments')->insertGetId([
                    'gym_id' => $membershipPlan->gym_id,
                    'membership_id' => $membership->id,
                    'mollie_payment_id' => 'tr_' . uniqid(),
                    'amount' => $membershipPlan->price,
                    'description' => $membershipPlan->name . ' - ' . $dueDate->format('m/Y'),
                    'status' => $status,
                    'due_date' => $dueDate->format('Y-m-d'),
                    'paid_date' => $paidDate ? $paidDate->format('Y-m-d') : null,
                    'payment_method' => rand(0, 1) ? 'sepa_direct_debit' : 'creditcard',
                    'transaction_id' => uniqid(),
                    'created_at' => $dueDate->subDays(5),
                    'updated_at' => $paidDate ?? $dueDate,
                ]);

                // Track paid payments for chargebacks/refunds
                if ($status === 'paid') {
                    $paidPayments[] = [
                        'id' => $paymentId,
                        'amount' => $membershipPlan->price,
                        'paid_date' => $paidDate,
                    ];
                }
            }
        }

        // Create chargebacks and refunds for 1-2% of paid payments
        $this->createChargebacksAndRefunds($paidPayments);
    }

    /**
     * Create chargebacks and refunds for 1-2% of paid payments.
     */
    private function createChargebacksAndRefunds(array $paidPayments): void
    {
        // Randomly select 1-2% of payments
        $percentage = rand(10, 20) / 1000; // 1% to 2%
        $count = max(1, (int) floor(count($paidPayments) * $percentage));

        // Shuffle and pick random payments
        shuffle($paidPayments);
        $selectedPayments = array_slice($paidPayments, 0, $count);

        $toggle = true; // Alternate between chargeback and refund

        foreach ($selectedPayments as $payment) {
            if ($toggle) {
                $this->createChargeback($payment);
            } else {
                $this->createRefund($payment);
            }
            $toggle = !$toggle;
        }
    }

    /**
     * Create a chargeback for a payment.
     */
    private function createChargeback(array $payment): void
    {
        $chargebackDate = Carbon::parse($payment['paid_date'])->addDays(rand(5, 45));

        // Update payment status to chargeback
        DB::table('payments')
            ->where('id', $payment['id'])
            ->update(['status' => 'chargeback']);

        DB::table('chargebacks')->insert([
            'payment_id' => $payment['id'],
            'mollie_chargeback_id' => 'chb_' . uniqid(),
            'amount' => $payment['amount'],
            'currency' => 'EUR',
            'status' => collect(['received', 'accepted', 'disputed'])->random(),
            'mollie_status' => 'chargeback',
            'reason' => $this->chargebackReasons[array_rand($this->chargebackReasons)],
            'chargeback_date' => $chargebackDate,
            'created_at' => $chargebackDate,
            'updated_at' => $chargebackDate,
        ]);
    }

    /**
     * Create a refund for a payment.
     */
    private function createRefund(array $payment): void
    {
        $refundDate = Carbon::parse($payment['paid_date'])->addDays(rand(1, 30));
        $isPartial = rand(0, 1) === 1;
        $refundAmount = $isPartial
            ? round($payment['amount'] * (rand(30, 70) / 100), 2)
            : $payment['amount'];

        // Update payment status
        DB::table('payments')
            ->where('id', $payment['id'])
            ->update(['status' => $isPartial ? 'partially_refunded' : 'refunded']);

        DB::table('refunds')->insert([
            'payment_id' => $payment['id'],
            'mollie_refund_id' => 're_' . uniqid(),
            'amount' => $refundAmount,
            'currency' => 'EUR',
            'description' => $isPartial ? 'Teilerstattung' : 'Vollständige Erstattung',
            'status' => 'refunded',
            'mollie_status' => 'refunded',
            'created_by' => null,
            'reason' => $this->refundReasons[array_rand($this->refundReasons)],
            'created_at' => $refundDate,
            'updated_at' => $refundDate,
        ]);
    }
}
