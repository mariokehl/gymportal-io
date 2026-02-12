<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Models\Membership;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateMembershipStatuses extends Command
{
    protected $signature = 'memberships:update-statuses';
    protected $description = 'Aktualisiert Mitgliedschaftsstatus basierend auf Kündigungs- und Pausierungsdaten';

    public function handle()
    {
        $this->info('Starte Aktualisierung der Mitgliedschaftsstatus...');

        $updated = 0;
        $now = Carbon::now();

        // 1. Gekündigte Mitgliedschaften auf 'cancelled' setzen
        $cancelledCount = Membership::where('cancellation_date', '<', $now)
            ->whereIn('status', ['active', 'paused'])
            ->update(['status' => 'cancelled']);

        if ($cancelledCount > 0) {
            $updated += $cancelledCount;
            $this->info("$cancelledCount Mitgliedschaft(en) wurden auf 'cancelled' gesetzt.");
        }

        // 2. Pausierte Mitgliedschaften automatisch wieder aktivieren
        $resumedCount = Membership::where('status', 'paused')
            ->where('pause_end_date', '<=', $now)
            ->update(['status' => 'active']);

        if ($resumedCount > 0) {
            $updated += $resumedCount;
            $this->info("$resumedCount pausierte Mitgliedschaft(en) wurden wieder aktiviert.");
        }

        // 3. Mitgliedschaften pausieren, deren Pausierungsdatum erreicht wurde
        $pausedCount = Membership::where('status', 'active')
            ->where('pause_start_date', '<=', $now)
            ->where('pause_end_date', '>', $now)
            ->update(['status' => 'paused']);

        if ($pausedCount > 0) {
            $updated += $pausedCount;
            $this->info("$pausedCount Mitgliedschaft(en) wurden pausiert.");
        }

        // 4. Abgelaufene Mitgliedschaften auf 'expired' setzen
        // Unbefristete Mitgliedschaften (end_date=null) werden nicht expired
        $expiredCount = Membership::where('status', 'active')
            ->whereNotNull('end_date')
            ->where('end_date', '<=', $now)
            ->whereNull('cancellation_date')
            ->update(['status' => 'expired']);

        if ($expiredCount > 0) {
            $updated += $expiredCount;
            $this->info("$expiredCount Mitgliedschaft(en) sind abgelaufen.");
        }

        // 5. Ausstehende Mitgliedschaften prüfen (z.B. nach 30 Tagen automatisch stornieren)
        $pendingTimeout = Membership::where('status', 'pending')
            ->where('created_at', '<=', $now->subDays(30))
            ->get();

        foreach ($pendingTimeout as $membership) {
            $membership->update([
                'status' => 'expired',
                'cancellation_reason' => 'Automatisch storniert - Aktivierung nicht abgeschlossen'
            ]);
            $updated++;

            Log::info("Ausstehende Mitgliedschaft #{$membership->id} wurde automatisch storniert.");
        }

        // 6. Mitglieder ohne aktive Mitgliedschaft auf 'inactive' setzen
        // Überspringe Mitglieder mit aktivem Gastzugang
        $activeMembers = Member::where('status', 'active')
            ->where('guest_access', false)
            ->get();
        $deactivatedCount = 0;

        foreach ($activeMembers as $member) {
            // Prüfen, ob Mitglied mindestens eine aktive Mitgliedschaft hat
            $hasActiveMembership = $member->memberships()
                ->where('status', 'active')
                ->exists();

            if (!$hasActiveMembership) {
                $member->logStatusChange(
                    'inactive',
                    'Automatisch deaktiviert - keine aktive Mitgliedschaft vorhanden',
                    ['action_source' => 'System']
                );
                $member->update(['status' => 'inactive']);
                $deactivatedCount++;
            }
        }

        if ($deactivatedCount > 0) {
            $this->info("$deactivatedCount Mitglied(er) ohne aktive Mitgliedschaft wurden auf 'inactive' gesetzt.");
        }

        // Mitglieder mit Gastzugang zählen (zur Information)
        $guestAccessCount = Member::where('status', 'active')
            ->where('guest_access', true)
            ->doesntHave('memberships', 'and', function ($query) {
                $query->where('status', 'active');
            })
            ->count();

        if ($guestAccessCount > 0) {
            $this->info("$guestAccessCount Mitglied(er) mit Gastzugang wurden übersprungen.");
        }

        $this->info("Aktualisierung abgeschlossen. $updated Mitgliedschaft(en) wurden aktualisiert.");

        return Command::SUCCESS;
    }
}
