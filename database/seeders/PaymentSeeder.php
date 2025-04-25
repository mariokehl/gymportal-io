<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PaymentSeeder extends Seeder
{
    public function run()
    {
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

                DB::table('payments')->insert([
                    'membership_id' => $membership->id,
                    'mollie_payment_id' => 'tr_' . uniqid(),
                    'amount' => $membershipPlan->price,
                    'description' => $membershipPlan->name . ' - ' . $dueDate->format('m/Y'),
                    'status' => $status,
                    'due_date' => $dueDate->format('Y-m-d'),
                    'paid_date' => $paidDate ? $paidDate->format('Y-m-d') : null,
                    'payment_method' => rand(0, 1) ? 'sepa' : 'creditcard',
                    'transaction_id' => uniqid(),
                    'created_at' => $dueDate->subDays(5),
                    'updated_at' => $paidDate ?? $dueDate,
                ]);
            }
        }
    }
}
