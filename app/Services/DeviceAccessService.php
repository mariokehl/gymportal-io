<?php

namespace App\Services;

use App\Models\GymScanner;
use App\Models\Member;
use App\Models\MemberAccessLog;
use App\Models\Membership;

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
     * @param  Membership|null  $membership  Null for guests, who have no membership.
     * @return array{0: bool, 1: string|null} [allowed, denial reason]
     */
    public function check(GymScanner $scanner, Member $member, ?Membership $membership): array
    {
        if (! $scanner->isAddonLinked()) {
            return [true, null];
        }

        // A device without a linked service cannot decide anything. Fail closed:
        // releasing the tap for everyone would be worse than a denied scan.
        if (! $scanner->addon_id) {
            return [false, self::DENIAL_ADDON_MISSING];
        }

        if (! $membership) {
            return [false, self::DENIAL_GUEST_WITHOUT_ADDON];
        }

        if (! $membership->hasActiveAddon($scanner->addon_id)) {
            return [false, self::DENIAL_ADDON_NOT_BOOKED];
        }

        return [true, null];
    }

    /**
     * Record the usage of a booked add-on in the member's access history.
     *
     * Only add-on devices are logged here: a door scan already lands in the
     * check-ins, and duplicating it would clutter the history. Denials are
     * recorded too — for the operator, a member repeatedly refused at the
     * dispenser is the interesting case.
     */
    public function logUsage(
        GymScanner $scanner,
        Member $member,
        string $scanType,
        bool $granted,
        ?string $denialReason = null
    ): void {
        if (! $scanner->isAddonLinked()) {
            return;
        }

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
                'addon_id' => $scanner->addon_id,
                'addon_name' => $scanner->addon?->name,
                'device_name' => $scanner->device_name,
                'device_task' => $scanner->device_task,
                'reason' => $denialReason,
            ], fn ($value) => $value !== null),
            // The history renders accessed_at, so it has to be set explicitly.
            'accessed_at' => now(),
        ]);
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
