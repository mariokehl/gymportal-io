<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PaymentMethodSeeder extends Seeder
{
    public function run()
    {
        // Get all members
        $members = DB::table('members')->get();

        foreach ($members as $member) {
            // Choose randomly between SEPA and credit card
            $type = rand(0, 1) ? 'sepa_direct_debit' : 'creditcard';

            if ($type === 'sepa_direct_debit') {
                DB::table('payment_methods')->insert([
                    'member_id' => $member->id,
                    'mollie_customer_id' => 'cst_' . uniqid(),
                    'mollie_mandate_id' => 'mdt_' . uniqid(),
                    'type' => 'sepa_direct_debit',
                    'bank_name' => $this->getRandomBank(),
                    'iban' => $this->generateRandomIBAN(),
                    'is_default' => true,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('payment_methods')->insert([
                    'member_id' => $member->id,
                    'mollie_customer_id' => 'cst_' . uniqid(),
                    'mollie_mandate_id' => 'mdt_' . uniqid(),
                    'type' => 'creditcard',
                    'last_four' => str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT),
                    'cardholder_name' => $member->first_name . ' ' . $member->last_name,
                    'expiry_date' => Carbon::now()->addYears(rand(1, 5))->format('Y-m-d'),
                    'is_default' => true,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function getRandomBank()
    {
        $banks = ['Deutsche Bank', 'Commerzbank', 'Sparkasse', 'Volksbank', 'DKB', 'ING-DiBa', 'Postbank', 'Hypovereinsbank'];
        return $banks[array_rand($banks)];
    }

    private function generateRandomIBAN()
    {
        return 'DE' . rand(10, 99) . ' ' . rand(1000, 9999) . ' ' . rand(1000, 9999) . ' ' . rand(1000, 9999) . ' ' . rand(10, 99);
    }
}
