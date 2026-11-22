<?php

namespace Database\Seeders;

use App\Models\CheckIn;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MemberAccessConfig;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Payment;
use Illuminate\Database\Seeder;

/**
 * Invented data to try the Agent API against: one studio, two plans, forty
 * members with visits and a few failed payments. No real person in here.
 *
 *   php artisan db:seed --class=AgentApiDemoSeeder
 */
class AgentApiDemoSeeder extends Seeder
{
    public function run(): void
    {
        mt_srand(20261112);

        $gym = Gym::factory()->create(['name' => 'Demo Studio', 'slug' => 'demo-studio']);

        $flex = MembershipPlan::factory()->create([
            'gym_id' => $gym->id, 'name' => 'Flex monatlich', 'price' => 39.90,
            'commitment_months' => 1, 'cancellation_period' => 1, 'cancellation_period_unit' => 'months', 'sort_order' => 1,
        ]);
        $year = MembershipPlan::factory()->create([
            'gym_id' => $gym->id, 'name' => 'Jahresvertrag 12', 'price' => 29.90,
            'commitment_months' => 12, 'cancellation_period' => 1, 'cancellation_period_unit' => 'months', 'sort_order' => 2,
        ]);

        for ($i = 1; $i <= 40; $i++) {
            $member = Member::factory()->create([
                'gym_id' => $gym->id,
                'status' => 'active',
                'member_number' => sprintf('D-%04d', $i),
                'email' => sprintf('mitglied%02d@demo.invalid', $i),
                'joined_date' => now()->subMonths(mt_rand(1, 20))->toDateString(),
            ]);

            $membership = Membership::factory()->create([
                'member_id' => $member->id,
                'membership_plan_id' => $i % 3 === 0 ? $flex->id : $year->id,
                'start_date' => $member->getRawOriginal('joined_date'),
                'status' => 'active',
            ]);

            MemberAccessConfig::create(['member_id' => $member->id, 'qr_code_enabled' => true]);

            // Every fifth member stopped coming six weeks ago.
            $lastActiveDaysAgo = $i % 5 === 0 ? 45 : 0;
            $visits = $i % 5 === 0 ? 4 : mt_rand(6, 24);

            for ($v = 0; $v < $visits; $v++) {
                $at = now()->subDays($lastActiveDaysAgo + mt_rand(1, 55))
                    ->setTime([6, 7, 9, 12, 17, 18, 18, 19, 20][mt_rand(0, 8)], mt_rand(0, 59));

                CheckIn::create([
                    'member_id' => $member->id,
                    'gym_id' => $gym->id,
                    'check_in_time' => $at,
                    'check_out_time' => $at->copy()->addMinutes(mt_rand(40, 95)),
                    'check_in_method' => 'qr_code',
                ]);
            }

            Payment::create([
                'gym_id' => $gym->id,
                'member_id' => $member->id,
                'membership_id' => $membership->id,
                'amount' => $membership->membershipPlan->price,
                'currency' => 'EUR',
                'description' => 'Mitgliedsbeitrag',
                'status' => $i % 8 === 0 ? 'failed' : 'paid',
                'due_date' => now()->startOfMonth()->toDateString(),
                'paid_date' => $i % 8 === 0 ? null : now()->startOfMonth()->toDateString(),
                'payment_method' => 'sepa_direct_debit',
            ]);
        }
    }
}
