<?php

namespace App\Services;

use App\Models\GuestProduct;
use App\Models\GymScanner;
use App\Models\Member;
use App\Models\MemberAccessConfig;
use App\Models\MemberAccessLog;
use App\Models\PendingSolariumRedemption;
use App\Models\ScannerAccessLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Redemption of solarium minutes.
 *
 * The guest scans the guest service QR code at the solarium terminal, then
 * starts the redemption in the guest app. The minutes are reserved right
 * away; the terminal polls for pending redemptions, switches the tanning bed
 * and acknowledges. Anything not acknowledged in time expires and the
 * reserved minutes go back to the guest.
 */
class SolariumRedemptionService
{
    /**
     * How long after the scan at the solarium a redemption may be started.
     */
    public const SCAN_VALIDITY_HOURS = 4;

    /**
     * Window in which the terminal polls more frequently after a scan.
     */
    public const HOT_POLL_MINUTES = 2;

    /**
     * Reserve minutes for a redemption.
     *
     * @throws RuntimeException with a guest-facing message when not possible
     */
    public function reserve(Member $member, int $minutes): PendingSolariumRedemption
    {
        if (! $member->hasReachedAge(GuestProduct::SOLARIUM_MIN_AGE)) {
            throw new RuntimeException('Das Solarium darfst du erst ab '.GuestProduct::SOLARIUM_MIN_AGE.' Jahren nutzen.');
        }

        $scan = $this->recentSolariumScan($member);

        if (! $scan) {
            throw new RuntimeException('Bitte scanne zuerst deinen QR-Code am Solarium.');
        }

        return DB::transaction(function () use ($member, $minutes, $scan) {
            $config = MemberAccessConfig::where('member_id', $member->id)->lockForUpdate()->first();

            if (! $config || ! $config->solarium_enabled || $config->solarium_minutes < $minutes) {
                throw new RuntimeException('Du hast nicht genügend Solarium-Minuten.');
            }

            $openRedemption = PendingSolariumRedemption::where('member_id', $member->id)
                ->where('status', PendingSolariumRedemption::STATUS_PENDING)
                ->exists();

            if ($openRedemption) {
                throw new RuntimeException('Es läuft bereits eine Einlösung. Bitte warte kurz oder brich sie ab.');
            }

            $remaining = $config->solarium_minutes - $minutes;
            $config->update([
                'solarium_minutes' => $remaining,
                'solarium_enabled' => $remaining > 0,
            ]);

            $redemption = PendingSolariumRedemption::create([
                'member_id' => $member->id,
                'gym_id' => $scan->gym_id,
                'minutes' => $minutes,
                'status' => PendingSolariumRedemption::STATUS_PENDING,
            ]);

            $this->log($member->id, MemberAccessLog::ACTION_CREDIT_CONSUMED, [
                'minutes' => $minutes,
                'reason' => 'redemption_reserved',
                'redemption_id' => $redemption->id,
            ]);

            return $redemption;
        });
    }

    /**
     * Cancel a pending redemption on the guest's request.
     *
     * @throws RuntimeException when the redemption is no longer pending
     */
    public function cancel(PendingSolariumRedemption $redemption): void
    {
        if (! $this->close($redemption, PendingSolariumRedemption::STATUS_CANCELLED, 'cancelled_by_guest')) {
            throw new RuntimeException('Die Einlösung ist nicht mehr offen.');
        }
    }

    /**
     * Pending redemptions the terminal has to process. Stale ones are expired
     * first, so the terminal never switches the bed for an outdated request.
     */
    public function pendingFor(GymScanner $scanner): Collection
    {
        $this->expireStale($scanner->gym_id);

        return PendingSolariumRedemption::where('gym_id', $scanner->gym_id)
            ->where('status', PendingSolariumRedemption::STATUS_PENDING)
            ->orderBy('created_at')
            ->get(['id', 'member_id', 'minutes', 'created_at']);
    }

    /**
     * Whether a guest scanned at a solarium of the gym moments ago, so the
     * terminal can poll more frequently.
     */
    public function hasRecentScan(GymScanner $scanner): bool
    {
        return ScannerAccessLog::where('gym_id', $scanner->gym_id)
            ->whereIn('device_number', $this->solariumDeviceNumbers($scanner->gym_id))
            ->where('scan_type', 'guest_service')
            ->where('access_granted', true)
            ->where('created_at', '>=', now()->subMinutes(self::HOT_POLL_MINUTES))
            ->exists();
    }

    /**
     * The terminal confirms that it switched the bed, or reports a failure;
     * a failure returns the minutes to the guest.
     *
     * @return bool false when the redemption was no longer pending
     */
    public function acknowledge(PendingSolariumRedemption $redemption, GymScanner $scanner, bool $completed, ?string $reason): bool
    {
        return $this->close(
            $redemption,
            $completed ? PendingSolariumRedemption::STATUS_COMPLETED : PendingSolariumRedemption::STATUS_FAILED,
            $reason ?? ($completed ? null : 'unknown'),
            $scanner
        );
    }

    /**
     * Expire pending redemptions that were not acknowledged in time.
     *
     * @return int number of expired redemptions
     */
    public function expireStale(?int $gymId = null): int
    {
        $stale = PendingSolariumRedemption::where('status', PendingSolariumRedemption::STATUS_PENDING)
            ->where('created_at', '<', now()->subSeconds(PendingSolariumRedemption::EXPIRY_SECONDS))
            ->when($gymId, fn ($query) => $query->where('gym_id', $gymId))
            ->get();

        $expired = 0;

        foreach ($stale as $redemption) {
            try {
                if ($this->close($redemption, PendingSolariumRedemption::STATUS_EXPIRED, 'not_acknowledged_in_time')) {
                    $expired++;
                }
            } catch (\Throwable $e) {
                Log::error('Expiring solarium redemption failed', [
                    'redemption_id' => $redemption->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $expired;
    }

    /**
     * Move a pending redemption into a final status. Every status but
     * "completed" returns the reserved minutes.
     */
    private function close(PendingSolariumRedemption $redemption, string $status, ?string $reason, ?GymScanner $scanner = null): bool
    {
        return DB::transaction(function () use ($redemption, $status, $reason, $scanner) {
            $locked = PendingSolariumRedemption::whereKey($redemption->id)->lockForUpdate()->first();

            if (! $locked || ! $locked->isPending()) {
                return false;
            }

            $locked->update([
                'status' => $status,
                'failure_reason' => $reason,
                'acknowledged_by_scanner_id' => $scanner?->id,
                'acknowledged_at' => $scanner ? now() : null,
            ]);

            if ($status !== PendingSolariumRedemption::STATUS_COMPLETED) {
                $this->returnMinutes($locked, $reason);
            }

            $redemption->setRawAttributes($locked->getAttributes(), true);

            return true;
        });
    }

    private function returnMinutes(PendingSolariumRedemption $redemption, ?string $reason): void
    {
        $config = MemberAccessConfig::where('member_id', $redemption->member_id)->lockForUpdate()->first();

        if (! $config) {
            return;
        }

        $config->update([
            'solarium_minutes' => (int) $config->solarium_minutes + $redemption->minutes,
            'solarium_enabled' => true,
        ]);

        $this->log($redemption->member_id, MemberAccessLog::ACTION_CREDIT_ADDED, [
            'minutes' => $redemption->minutes,
            'reason' => 'redemption_'.$redemption->status.($reason ? ': '.$reason : ''),
            'redemption_id' => $redemption->id,
        ]);
    }

    /**
     * The guest's latest successful scan at a solarium of their own gym.
     */
    private function recentSolariumScan(Member $member): ?ScannerAccessLog
    {
        return ScannerAccessLog::where('gym_id', $member->gym_id)
            ->where('member_id', $member->id)
            ->whereIn('device_number', $this->solariumDeviceNumbers($member->gym_id))
            ->where('scan_type', 'guest_service')
            ->where('access_granted', true)
            ->where('created_at', '>=', now()->subHours(self::SCAN_VALIDITY_HOURS))
            ->latest('created_at')
            ->first();
    }

    private function solariumDeviceNumbers(int $gymId): array
    {
        return GymScanner::where('gym_id', $gymId)
            ->where('device_task', GymScanner::TASK_SOLARIUM)
            ->pluck('device_number')
            ->all();
    }

    private function log(int $memberId, string $action, array $metadata): void
    {
        MemberAccessLog::create([
            'member_id' => $memberId,
            'action' => $action,
            'success' => true,
            'method' => MemberAccessLog::METHOD_QR,
            'service' => MemberAccessLog::SERVICE_SOLARIUM,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => $metadata,
        ]);
    }
}
