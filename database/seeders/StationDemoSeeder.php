<?php

namespace Database\Seeders;

use App\Models\Gym;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use Illuminate\Database\Seeder;

/**
 * Local demo data for trying the printed check-in station by hand.
 *
 * Not part of DatabaseSeeder — run it explicitly:
 *   php artisan db:seed --class=StationDemoSeeder
 */
class StationDemoSeeder extends Seeder
{
    public function run(): void
    {
        // Built through the factories so every column the gyms table requires
        // gets a value — the model has a good deal more than the demo cares about.
        $gym = Gym::where('slug', 'station-demo')->first()
            ?? Gym::factory()->create(['slug' => 'station-demo', 'name' => 'Station Demo Studio']);

        $gym->update(['pwa_enabled' => true, 'checkin_station_enabled' => true]);
        $token = $gym->rotateCheckinStationToken();

        $member = Member::where('email', 'station-demo@example.com')->first()
            ?? Member::factory()->create([
                'gym_id' => $gym->id,
                'email' => 'station-demo@example.com',
                'first_name' => 'Stefanie',
                'last_name' => 'Demo',
                'status' => 'active',
            ]);

        $plan = MembershipPlan::where('gym_id', $gym->id)->where('name', 'Station Demo Tarif')->first()
            ?? MembershipPlan::factory()->create(['gym_id' => $gym->id, 'name' => 'Station Demo Tarif']);

        if (! Membership::where('member_id', $member->id)->where('status', 'active')->exists()) {
            Membership::factory()->create([
                'member_id' => $member->id,
                'membership_plan_id' => $plan->id,
                'status' => 'active',
                'start_date' => now()->subMonth()->toDateString(),
            ]);
        }

        $this->command->info('Gym slug:      '.$gym->slug);
        $this->command->info('Member email:  '.$member->email);
        $this->command->info('Scan URL:      /'.$gym->slug.'/scan?t='.$token);
    }
}
