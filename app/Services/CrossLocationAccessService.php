<?php

namespace App\Services;

use App\Models\Gym;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\ScannerAccessLog;

/**
 * Decides whether a member may check in at a location that is not their own.
 *
 * Two independent rules have to agree, and both default to "no":
 *
 *   1. the visited location accepts members from the member's home location
 *      (Gym::cross_location_checkin_rule)
 *   2. the member's contract covers the visited location
 *      (MembershipPlan::location_scope)
 *
 * Either one alone is not enough. Keeping them separate lets an operator open a
 * location for the organisation without every contract suddenly being valid
 * there, and lets a premium contract exist without forcing every location to
 * honour it.
 */
class CrossLocationAccessService
{
    /**
     * Result of a denied check: the reason shown to the operator and the kind
     * of denial, which drives the shortcut offered in the live log.
     */
    public const REASON_LOCATION = 'location';

    public const REASON_CONTRACT = 'contract';

    /**
     * Whether $member may check in at $gym.
     *
     * @param  Membership|null  $membership  The member's active membership. Guests
     *                                       have none; they are only ever admitted
     *                                       at their own location.
     * @return array{0: bool, 1: string|null, 2: string|null} allowed, denial reason, denial kind
     */
    public function check(Gym $gym, Member $member, ?Membership $membership): array
    {
        // The member's own location is always allowed — neither rule applies.
        if ($member->gym_id === $gym->id) {
            return [true, null, null];
        }

        if (! $gym->acceptsMembersFrom($member->gym_id)) {
            $homeName = Gym::whereKey($member->gym_id)->value('name') ?? 'Unbekannter Standort';

            return [
                false,
                "Standort {$homeName} ist hier nicht freigegeben.",
                self::REASON_LOCATION,
            ];
        }

        // Guest access carries no contract, so there is nothing that could cover
        // another location.
        $plan = $membership?->membershipPlan;

        if (! $plan) {
            return [
                false,
                'Ohne Vertrag ist nur ein Check-in am Heimatstandort möglich.',
                self::REASON_CONTRACT,
            ];
        }

        if (! $plan->coversGym($gym->id, $member->gym_id)) {
            return [
                false,
                "Vertrag „{$plan->name}“ gilt nicht für diesen Standort.",
                self::REASON_CONTRACT,
            ];
        }

        return [true, null, null];
    }

    /**
     * The locations of $gym's organisation, with the rule each one applies.
     * Used by the configuration screen and the contract's location tab.
     *
     * @return array<int, array{id: int, name: string, address: string|null, city: string|null, is_current: bool, rule: string, members_count: int}>
     */
    public function organizationLocations(Gym $gym): array
    {
        return $gym->organizationGyms()
            ->withCount('members')
            ->get()
            ->map(fn (Gym $location) => [
                'id' => $location->id,
                'name' => $location->name,
                'address' => $location->address,
                'city' => $location->city,
                'is_current' => $location->id === $gym->id,
                'rule' => $location->checkinRule(),
                'members_count' => $location->members_count,
            ])
            ->values()
            ->all();
    }

    /**
     * Preview for the contract's "Standorte" tab: where a member of $gym holding
     * a plan with $scope/$allowedGymIds could actually check in.
     *
     * Mirrors check() so the operator sees the same verdict the scanner would
     * reach, including which of the two rules blocks it.
     *
     * @param  array<int>  $allowedGymIds
     * @return array<int, array{id: int, name: string, allowed: bool, reason: string}>
     */
    public function contractEffect(Gym $gym, string $scope, array $allowedGymIds): array
    {
        return $gym->organizationGyms()->get()
            ->map(function (Gym $location) use ($gym, $scope, $allowedGymIds) {
                if ($location->id === $gym->id) {
                    return [
                        'id' => $location->id,
                        'name' => $location->name,
                        'allowed' => true,
                        'reason' => 'Heimatstandort',
                    ];
                }

                $contractOk = $scope === MembershipPlan::SCOPE_ALL
                    || ($scope === MembershipPlan::SCOPE_SELECTED && in_array($location->id, $allowedGymIds, true));
                $locationOk = $location->acceptsMembersFrom($gym->id);

                $reason = match (true) {
                    ! $contractOk => 'Vertrag erlaubt nicht',
                    ! $locationOk => 'Standort erlaubt nicht',
                    default => 'Vertrag + Standort erlauben',
                };

                return [
                    'id' => $location->id,
                    'name' => $location->name,
                    'allowed' => $contractOk && $locationOk,
                    'reason' => $reason,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Today's cross-location numbers for the live log header.
     *
     * @return array{checkins: int, guests: int, denied: int, guests_granted: int, breakdown: array<int, array{name: string, granted: int, total: int}>}
     */
    public function todaysSummary(Gym $gym): array
    {
        $logs = ScannerAccessLog::forGym($gym->id)->today()->get();

        $foreign = $logs->filter(fn ($log) => $log->isCrossLocation());

        $breakdown = $gym->organizationGyms()
            ->where('gyms.id', '!=', $gym->id)
            ->get()
            ->map(function (Gym $location) use ($foreign) {
                $rows = $foreign->where('home_gym_id', $location->id);

                return [
                    'name' => $location->name,
                    'granted' => $rows->where('access_granted', true)->count(),
                    'total' => $rows->count(),
                ];
            })
            ->values()
            ->all();

        return [
            'checkins' => $logs->where('access_granted', true)->count(),
            'guests' => $foreign->count(),
            'denied' => $logs->where('access_granted', false)->count(),
            'guests_granted' => $foreign->where('access_granted', true)->count(),
            'breakdown' => $breakdown,
        ];
    }
}
