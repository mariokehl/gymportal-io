<?php

namespace App\Services;

use App\Models\CheckIn;
use App\Models\Gym;
use App\Models\Member;
use App\Models\ScannerAccessLog;
use Carbon\Carbon;

/**
 * Check-in and check-out driven by the member's own phone, scanning the printed
 * QR code standing at the counter.
 *
 * This is the hardware-free counterpart to ScannerController::verifyMembership.
 * The access rules are deliberately the same ones — active member, running
 * membership, cross-location permission — because a gym must not become easier
 * to enter just because it prints its station instead of mounting a scanner.
 *
 * What it cannot do is prove presence. A printed code can be photographed, and
 * anyone holding the picture can scan it from home. A gym that needs real
 * presence needs hardware.
 */
class StationCheckInService
{
    /**
     * Scan type recorded in the access log, so the operator can tell a phone
     * scan apart from a device scan in the live view.
     */
    public const SCAN_TYPE = ScannerAccessLog::SCAN_TYPE_MANUAL;

    /**
     * A second scan within this window repeats the previous answer instead of
     * toggling back. Members hold the phone to the code, see nothing happen,
     * and scan again — without this, that second scan would check them straight
     * back out.
     */
    private const DEBOUNCE_SECONDS = 45;

    /**
     * How long a check-in stays open for a check-out. Matches the six hours the
     * PWA and CheckInController already apply; past that the visit is treated
     * as ended and a scan starts a new one.
     */
    private const MAX_SESSION_HOURS = 6;

    public function __construct(
        private CrossLocationAccessService $crossLocationService
    ) {}

    /**
     * Toggle the member's presence at $gym.
     *
     * The direction is decided here rather than by the member: an open check-in
     * closes, anything else opens a new one. The printed sheet therefore needs
     * no separate in/out codes, and there is no wrong code to scan.
     *
     * @return array{action: string, checkin: CheckIn|null, message: string, code: string|null, status: int}
     */
    public function toggle(Gym $gym, Member $member): array
    {
        // An open check-in is closed regardless of the guards below: a member
        // standing inside must always be able to check out, even once their
        // membership has lapsed.
        $open = $this->openCheckIn($member, $gym);

        if ($open) {
            return $this->closeOrRepeat($gym, $member, $open);
        }

        if ($denial = $this->guard($gym, $member)) {
            return $denial;
        }

        return $this->open($gym, $member);
    }

    /**
     * The member's currently open visit at this gym, if any.
     *
     * Scoped to the gym so a forgotten check-out at one location cannot end up
     * being closed by a scan at another.
     */
    public function openCheckIn(Member $member, Gym $gym): ?CheckIn
    {
        return CheckIn::where('member_id', $member->id)
            ->where('gym_id', $gym->id)
            ->whereNull('check_out_time')
            ->where('check_in_time', '>=', now()->subHours(self::MAX_SESSION_HOURS))
            ->orderByDesc('check_in_time')
            ->first();
    }

    /**
     * Close an open visit — unless it only just started, in which case the scan
     * is read as a repeat of the check-in that opened it.
     *
     * @return array{action: string, checkin: CheckIn|null, message: string, code: string|null, status: int}
     */
    private function closeOrRepeat(Gym $gym, Member $member, CheckIn $open): array
    {
        if ($open->check_in_time->gt(now()->subSeconds(self::DEBOUNCE_SECONDS))) {
            return [
                'action' => 'already_checked_in',
                'checkin' => $open,
                'message' => 'Du bist bereits eingecheckt.',
                'code' => null,
                'status' => 200,
            ];
        }

        $open->update(['check_out_time' => now()]);
        $open->refresh();

        // Deliberately unlogged: the live log shows access decisions, and a
        // check-out grants no access. Logging it would also double every visit
        // in the operator's view and inflate the "check-ins today" count. The
        // check-out itself is recorded on the CheckIn row.

        return [
            'action' => 'checked_out',
            'checkin' => $open,
            'message' => 'Check-out erfolgreich. Bis zum nächsten Mal!',
            'code' => null,
            'status' => 200,
        ];
    }

    /**
     * Record a new visit against the scanned location — not the member's home
     * gym, since the visit is happening here.
     *
     * @return array{action: string, checkin: CheckIn, message: string, code: null, status: int}
     */
    private function open(Gym $gym, Member $member): array
    {
        $checkin = CheckIn::create([
            'member_id' => $member->id,
            'gym_id' => $gym->id,
            'check_in_time' => now(),
            'check_in_method' => 'qr_code',
        ]);

        $this->log($gym, $member, true, null, ['action' => 'check_in']);

        return [
            'action' => 'checked_in',
            'checkin' => $checkin,
            'message' => 'Check-in erfolgreich. Viel Erfolg beim Training!',
            'code' => null,
            'status' => 200,
        ];
    }

    /**
     * Every reason a check-in may be refused, in the order an operator would
     * explain them. Returns null when the member may enter.
     *
     * @return array{action: string, checkin: null, message: string, code: string, status: int}|null
     */
    private function guard(Gym $gym, Member $member): ?array
    {
        if (! $member->isActive()) {
            return $this->deny(
                $gym,
                $member,
                'MEMBER_INACTIVE',
                'Dein Mitgliedskonto ist derzeit nicht aktiv. Bitte wende dich an das Studio-Team.',
                403
            );
        }

        // Guests hold no contract. They are admitted, but only at the location
        // they belong to — the cross-location check denies every other one.
        $membership = $member->hasGuestAccess() ? null : $member->activeMembership();

        if (! $member->hasGuestAccess()) {
            if (! $membership) {
                return $this->deny(
                    $gym,
                    $member,
                    'NO_ACTIVE_MEMBERSHIP',
                    'Für dein Konto ist keine aktive Mitgliedschaft hinterlegt.',
                    403
                );
            }

            if ($membership->start_date->isFuture()) {
                return $this->deny(
                    $gym,
                    $member,
                    'MEMBERSHIP_NOT_STARTED',
                    'Deine Mitgliedschaft startet am '.$membership->start_date->format('d.m.Y').'.',
                    403
                );
            }
        }

        [$allowed, $reason, $kind] = $this->crossLocationService->check($gym, $member, $membership);

        if (! $allowed) {
            return $this->deny(
                $gym,
                $member,
                'LOCATION_NOT_ALLOWED',
                $reason ?? 'Check-in an diesem Standort ist nicht möglich.',
                403,
                ['denial_kind' => $kind]
            );
        }

        return null;
    }

    /**
     * Log the refusal and shape the response in one step, so no denial path can
     * answer the member without leaving a trace for the operator.
     *
     * @param  array<string, mixed>  $context
     * @return array{action: string, checkin: null, message: string, code: string, status: int}
     */
    private function deny(
        Gym $gym,
        Member $member,
        string $code,
        string $message,
        int $status,
        array $context = []
    ): array {
        $this->log($gym, $member, false, $message, $context + ['action' => 'check_in', 'code' => $code]);

        return [
            'action' => 'denied',
            'checkin' => null,
            'message' => $message,
            'code' => $code,
            'status' => $status,
        ];
    }

    /**
     * Write to the same access log the scanners use, so phone check-ins show up
     * in the studio's live view alongside device scans.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function log(Gym $gym, Member $member, bool $granted, ?string $reason, array $metadata = []): void
    {
        ScannerAccessLog::create([
            'gym_id' => $gym->id,
            'device_number' => null,
            'member_id' => $member->id,
            'home_gym_id' => $member->gym_id,
            'scan_type' => self::SCAN_TYPE,
            'access_granted' => $granted,
            'denial_reason' => $granted ? null : $reason,
            'metadata' => $metadata + [
                'ip' => request()->ip(),
                'source' => self::SCAN_TYPE,
                'timestamp' => Carbon::now()->toIso8601String(),
            ],
        ]);
    }
}
