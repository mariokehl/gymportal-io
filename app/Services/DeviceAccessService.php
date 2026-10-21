<?php

namespace App\Services;

use App\Models\Addon;
use App\Models\Gym;
use App\Models\GymScanner;
use App\Models\Member;
use App\Models\MemberAccessLog;
use App\Models\Membership;
use Illuminate\Database\Eloquent\Builder;

/**
 * Decides whether a device may serve a member.
 *
 * Passing the membership check only answers "may this person be here". Devices
 * bound to a usage add-on — a drink dispenser, an area control — additionally
 * require that the member actually booked that service.
 */
class DeviceAccessService
{
    /**
     * Guests hold a day pass or a punch card; add-ons are sold with a
     * membership only, so a guest can never draw from an add-on device.
     */
    public const DENIAL_GUEST_WITHOUT_ADDON = 'Add-ons stehen nur Mitgliedern zur Verfügung';

    public const DENIAL_ADDON_NOT_BOOKED = 'Leistung nicht gebucht';

    public const DENIAL_ADDON_MISSING = 'Gerät ist mit keiner Leistung verknüpft';

    /**
     * Check a member against the task of the scanning device.
     *
     * Only devices bound to a usage add-on impose an extra condition; every
     * other task passes through, because the membership check already ran.
     *
     * A device may settle against several add-ons. The member is served if
     * they may use any of them; the third element names the one that matched,
     * so the usage is booked against the add-on actually drawn from.
     *
     * @param  Membership|null  $membership  Null for guests, who have no membership.
     * @return array{0: bool, 1: string|null, 2: int|null} [allowed, denial reason, matched add-on ID]
     */
    public function check(GymScanner $scanner, Member $member, ?Membership $membership): array
    {
        if (! $scanner->isAddonLinked()) {
            return [true, null, null];
        }

        // A device without a linked service cannot decide anything. Fail closed:
        // releasing the tap for everyone would be worse than a denied scan.
        if ($scanner->linkedAddonIds() === []) {
            return [false, self::DENIAL_ADDON_MISSING, null];
        }

        if (! $membership) {
            return [false, self::DENIAL_GUEST_WITHOUT_ADDON, null];
        }

        // Asked on the member, not on the scanned membership: during a free
        // trial period bridging the gap to a paid contract, the add-on hangs on
        // the paid membership while the trial one is the active contract today.
        $addonId = $member->firstActiveAddonId($this->eligibleAddonIds($scanner, $member));

        if (! $addonId) {
            return [false, self::DENIAL_ADDON_NOT_BOOKED, null];
        }

        return [true, null, $addonId];
    }

    /**
     * The linked add-ons that may serve this member, in link order.
     *
     * An add-on counts when it belongs to the device's location or to the
     * member's home location. A sibling's add-on therefore only ever serves
     * that sibling's members, and those reach this point only after
     * CrossLocationAccessService let them in — so a link left over after the
     * operator closed the location for them serves nobody.
     *
     * @return array<int>
     */
    private function eligibleAddonIds(GymScanner $scanner, Member $member): array
    {
        $linked = $scanner->linkedAddonIds();

        $gymIds = array_unique([(int) $scanner->gym_id, (int) $member->gym_id]);

        $eligible = Addon::whereIn('id', $linked)
            ->whereIn('gym_id', $gymIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_filter($linked, fn ($id) => in_array($id, $eligible, true)));
    }

    /**
     * Record the usage of a booked add-on in the member's access history.
     *
     * Only add-on devices are logged here: a door scan already lands in the
     * check-ins, and duplicating it would clutter the history. Denials are
     * recorded too — for the operator, a member repeatedly refused at the
     * dispenser is the interesting case.
     *
     * @param  int|null  $addonId  The add-on drawn from, as matched by check().
     *                             Defaults to the device's first linked add-on.
     */
    public function logUsage(
        GymScanner $scanner,
        Member $member,
        string $scanType,
        bool $granted,
        ?string $denialReason = null,
        ?int $addonId = null
    ): void {
        if (! $scanner->isAddonLinked()) {
            return;
        }

        $addonId ??= $scanner->linkedAddonIds()[0] ?? null;

        MemberAccessLog::create([
            'member_id' => $member->id,
            'action' => MemberAccessLog::ACTION_ACCESS_ATTEMPT,
            'service' => MemberAccessLog::SERVICE_ADDON,
            'method' => $this->accessMethod($scanType),
            'success' => $granted,
            'device_id' => $scanner->device_number,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'metadata' => array_filter([
                'addon_id' => $addonId,
                'addon_name' => $addonId ? $this->addonName($scanner, $addonId) : null,
                'device_name' => $scanner->device_name,
                'device_task' => $scanner->device_task,
                'reason' => $denialReason,
            ], fn ($value) => $value !== null),
            // The history renders accessed_at, so it has to be set explicitly.
            'accessed_at' => now(),
        ]);
    }

    private function addonName(GymScanner $scanner, int $addonId): ?string
    {
        return $scanner->addons->firstWhere('id', $addonId)?->name
            ?? ((int) $scanner->addon_id === $addonId ? $scanner->addon?->name : null);
    }

    /**
     * Usage add-ons a device of $gym may be linked to.
     *
     * Its own location's, plus those of every sibling location whose members
     * $gym admits under its cross-location rule — a visitor from Munich may
     * draw on the drink package booked at home only where Munich members are
     * let in at all. Nothing outside the organisation is ever offered.
     */
    public function linkableAddons(Gym $gym): Builder
    {
        $gymIds = $gym->organizationGyms()
            ->where('gyms.id', '!=', $gym->id)
            ->pluck('id')
            ->filter(fn ($id) => $gym->acceptsMembersFrom((int) $id))
            ->push($gym->id)
            ->all();

        return Addon::whereIn('gym_id', $gymIds)->usageServices();
    }

    /**
     * Map the scanner's scan type onto the access method of the log.
     */
    private function accessMethod(string $scanType): string
    {
        return $scanType === 'nfc_card'
            ? MemberAccessLog::METHOD_NFC
            : MemberAccessLog::METHOD_QR;
    }
}
