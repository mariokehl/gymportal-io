<?php

namespace App\Console\Commands;

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
        $expiredCount = Membership::where('status', 'active')
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

        $this->info("Aktualisierung abgeschlossen. $updated Mitgliedschaft(en) wurden aktualisiert.");

        return Command::SUCCESS;
    }
}
