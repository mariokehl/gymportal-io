<?php

namespace App\Services;

use App\Models\CheckIn;
use App\Models\Gym;
use App\Models\Member;
use App\Models\ScannerAccessLog;
use App\Models\User;
use Carbon\Carbon;

/**
 * Check-in and check-out performed by a staff member from the member's detail
 * page in the backend.
 *
 * Unlike StationCheckInService this deliberately runs *no* access guards. The
 * operator standing at the counter can see who is in front of them and already
 * decided to let them in; a blocked account or a lapsed membership is a
 * conversation they are having in person, not something this endpoint should
 * override them on. Every check-in is attributed to the acting user, so the
 * decision stays traceable.
 */
class StaffCheckInService
{
    /**
     * How long a check-in stays open for a check-out. Matches the six hours
     * StationCheckInService and the PWA already apply, so a visit opened at the
     * counter and closed by the member's phone agree on when it expired.
     */
    private const MAX_SESSION_HOURS = 6;

    /**
     * Toggle the member's presence at $gym on behalf of $user.
     *
     * The direction follows the member's current state rather than a parameter
     * from the client: an open visit closes, anything else opens a new one.
     * That way the button in the UI cannot disagree with the database if two
     * staff members act at the same time.
     *
     * @return array{action: string, checkin: CheckIn, message: string}
     */
    public function toggle(Gym $gym, Member $member, User $user): array
    {
        $open = $this->openCheckIn($member, $gym);

        if ($open) {
            return $this->close($open);
        }

        return $this->open($gym, $member, $user);
    }

    /**
     * The member's currently open visit at this gym, if any.
     *
     * Scoped to the gym so a forgotten check-out at one location cannot be
     * closed from another location's member page.
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
     * Record a new visit, attributed to the staff member who opened it.
     *
     * @return array{action: string, checkin: CheckIn, message: string}
     */
    private function open(Gym $gym, Member $member, User $user): array
    {
        $checkin = CheckIn::create([
            'member_id' => $member->id,
            'gym_id' => $gym->id,
            'check_in_time' => now(),
            'check_in_method' => 'manual',
            'checked_in_by' => $user->id,
        ]);

        $this->log($gym, $member, $user);

        return [
            'action' => 'checked_in',
            'checkin' => $checkin,
            'message' => 'Mitglied wurde eingecheckt.',
        ];
    }

    /**
     * Close an open visit.
     *
     * No debounce here, unlike the printed station: a staff member clicking the
     * menu entry twice means it, whereas a member holding a phone to a code
     * often scans twice by accident.
     *
     * @return array{action: string, checkin: CheckIn, message: string}
     */
    private function close(CheckIn $open): array
    {
        $open->update(['check_out_time' => now()]);
        $open->refresh();

        // Deliberately unlogged, matching StationCheckInService: the access log
        // records access decisions, and a check-out grants no access. Logging it
        // would double every visit in the operator's live view.

        return [
            'action' => 'checked_out',
            'checkin' => $open,
            'message' => 'Mitglied wurde ausgecheckt.',
        ];
    }

    /**
     * Write to the same access log the scanners use, so a counter check-in shows
     * up in the studio's live view alongside device and station scans. The
     * acting user is recorded in the metadata — this is the one check-in path
     * where a human, not a device, made the call.
     */
    private function log(Gym $gym, Member $member, User $user): void
    {
        ScannerAccessLog::create([
            'gym_id' => $gym->id,
            'device_number' => null,
            'member_id' => $member->id,
            'home_gym_id' => $member->gym_id,
            'scan_type' => ScannerAccessLog::SCAN_TYPE_MANUAL,
            'access_granted' => true,
            'denial_reason' => null,
            'metadata' => [
                'action' => 'check_in',
                'source' => 'staff',
                'performed_by' => $user->id,
                'performed_by_name' => $user->fullName(),
                'ip' => request()->ip(),
                'timestamp' => Carbon::now()->toIso8601String(),
            ],
        ]);
    }
}
