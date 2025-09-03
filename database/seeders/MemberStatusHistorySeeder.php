<?php

namespace Database\Seeders;

use App\Models\Member;
use App\Models\MemberStatusHistory;
use App\Models\User;
use Illuminate\Database\Seeder;

class MemberStatusHistorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $members = Member::all();
        $users = User::all();

        if ($members->isEmpty() || $users->isEmpty()) {
            return;
        }

        foreach ($members as $member) {
            // Erstelle eine realistische Status-Historie
            $this->createRealisticHistory($member, $users);
        }
    }

    /**
     * Create realistic status history for a member
     */
    private function createRealisticHistory(Member $member, $users): void
    {
        $statuses = ['pending', 'active', 'paused', 'active', 'overdue', 'active'];
        $currentDate = now()->subMonths(6);

        // Initiale Registrierung
        MemberStatusHistory::create([
            'member_id' => $member->id,
            'old_status' => 'inactive',
            'new_status' => 'pending',
            'reason' => 'Neue Registrierung',
            'changed_by' => null,
            'metadata' => [
                'triggered_by' => 'registration',
                'action_source' => 'system',
                'registration_source' => fake()->randomElement(['website', 'admin', 'widget'])
            ],
            'created_at' => $currentDate,
            'updated_at' => $currentDate
        ]);

        $previousStatus = 'pending';
        $currentDate->addDays(rand(1, 3));

        // Aktivierung nach Zahlungsbestätigung
        if (fake()->boolean(80)) { // 80% werden aktiviert
            MemberStatusHistory::create([
                'member_id' => $member->id,
                'old_status' => $previousStatus,
                'new_status' => 'active',
                'reason' => 'Erste Zahlung eingegangen und SEPA-Mandat aktiv',
                'changed_by' => $users->random()->id,
                'metadata' => [
                    'action_source' => 'manual_update',
                    'payment_confirmed' => true,
                    'sepa_mandate_active' => true
                ],
                'created_at' => $currentDate,
                'updated_at' => $currentDate
            ]);

            $previousStatus = 'active';
            $currentDate->addMonths(rand(1, 3));

            // Mögliche Pausierung
            if (fake()->boolean(30)) {
                MemberStatusHistory::create([
                    'member_id' => $member->id,
                    'old_status' => $previousStatus,
                    'new_status' => 'paused',
                    'reason' => fake()->randomElement([
                        'Urlaub',
                        'Verletzung',
                        'Berufliche Gründe',
                        'Persönliche Auszeit'
                    ]),
                    'changed_by' => $users->random()->id,
                    'metadata' => [
                        'action_source' => 'manual_update',
                        'pause_duration_days' => rand(14, 60)
                    ],
                    'created_at' => $currentDate,
                    'updated_at' => $currentDate
                ]);

                $previousStatus = 'paused';
                $currentDate->addDays(rand(14, 60));

                // Wiederaufnahme
                MemberStatusHistory::create([
                    'member_id' => $member->id,
                    'old_status' => $previousStatus,
                    'new_status' => 'active',
                    'reason' => 'Pausierung beendet',
                    'changed_by' => $users->random()->id,
                    'metadata' => [
                        'action_source' => 'manual_update'
                    ],
                    'created_at' => $currentDate,
                    'updated_at' => $currentDate
                ]);

                $previousStatus = 'active';
                $currentDate->addMonths(rand(1, 2));
            }

            // Mögliche Zahlungsprobleme
            if (fake()->boolean(20)) {
                MemberStatusHistory::create([
                    'member_id' => $member->id,
                    'old_status' => $previousStatus,
                    'new_status' => 'overdue',
                    'reason' => 'Lastschrift fehlgeschlagen',
                    'changed_by' => null,
                    'metadata' => [
                        'triggered_by' => 'payment_overdue',
                        'action_source' => 'system',
                        'failed_payment_attempts' => rand(1, 3),
                        'amount_due' => fake()->randomFloat(2, 29, 99)
                    ],
                    'created_at' => $currentDate,
                    'updated_at' => $currentDate
                ]);

                $previousStatus = 'overdue';
                $currentDate->addDays(rand(3, 14));

                // Zahlung eingegangen
                if (fake()->boolean(70)) {
                    MemberStatusHistory::create([
                        'member_id' => $member->id,
                        'old_status' => $previousStatus,
                        'new_status' => 'active',
                        'reason' => 'Zahlung eingegangen',
                        'changed_by' => null,
                        'metadata' => [
                            'triggered_by' => 'payment_resolved',
                            'action_source' => 'system',
                            'payment_method' => fake()->randomElement(['bank_transfer', 'sepa_retry', 'cash'])
                        ],
                        'created_at' => $currentDate,
                        'updated_at' => $currentDate
                    ]);
                    $previousStatus = 'active';
                }
            }
        }

        // Aktualisiere den finalen Status des Mitglieds
        $member->update(['status' => $previousStatus]);
    }
}
