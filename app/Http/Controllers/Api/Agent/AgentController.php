<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Models\GymApiClient;
use App\Models\Member;
use App\Models\Membership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Shared plumbing of the Agent API: tenant resolution, response envelope and
 * the shapes handed to agents.
 *
 * The shapes are deliberately narrower than the Eloquent models. An agent gets
 * what it needs to answer a member, not every column of the table.
 */
abstract class AgentController extends Controller
{
    public const VERSION = '1.0.0';

    protected function client(Request $request): GymApiClient
    {
        return $request->attributes->get('agent_client');
    }

    protected function gym(Request $request): Gym
    {
        return $request->attributes->get('agent_gym');
    }

    /**
     * Resolves a member strictly inside the gym of the token. A member id of
     * another gym is indistinguishable from one that does not exist.
     */
    protected function member(Request $request, int|string $memberId): ?Member
    {
        $member = Member::where('gym_id', $this->gym($request)->id)
            ->whereKey($memberId)
            ->first();

        if ($member) {
            $request->attributes->set('agent_member_id', $member->id);
        }

        return $member;
    }

    /**
     * The contract a cancellation, pause or withdrawal refers to: the active or
     * paused membership, and during a free period the paid membership linked
     * to it, as in the member app.
     */
    protected function contractMembership(Member $member): ?Membership
    {
        $active = $member->activeMembership()
            ?? $member->memberships()->where('status', 'paused')->orderByDesc('start_date')->first();

        if (! $active) {
            return null;
        }

        return $active->is_free_trial ? $active->linkedPaidMembership : $active;
    }

    protected function ok(array $data, array $meta = []): JsonResponse
    {
        return response()->json(array_filter([
            'success' => true,
            'data' => $data,
            'meta' => $meta ?: null,
        ], static fn ($value) => $value !== null));
    }

    protected function fail(string $code, string $message, int $status, array $extra = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => ['code' => $code, 'message' => $message] + $extra,
        ], $status);
    }

    protected function memberNotFound(): JsonResponse
    {
        return $this->fail('member_not_found', 'No such member in this gym.', 404);
    }

    protected function presentMember(Member $member): array
    {
        return [
            'id' => $member->id,
            'member_number' => $member->member_number,
            'salutation' => $member->salutation,
            'first_name' => $member->first_name,
            'last_name' => $member->last_name,
            'email' => $member->email,
            'phone' => $member->phone,
            'birth_date' => optional($member->birth_date)->toDateString() ?? $this->dateString($member->getRawOriginal('birth_date')),
            'address' => $member->address,
            'address_addition' => $member->address_addition,
            'postal_code' => $member->postal_code,
            'city' => $member->city,
            'country' => $member->country,
            'status' => $member->status,
            'joined_date' => $this->dateString($member->getRawOriginal('joined_date')),
        ];
    }

    protected function presentMembership(Membership $membership): array
    {
        $plan = $membership->membershipPlan;

        return [
            'id' => $membership->id,
            'status' => $membership->status,
            'is_free_trial' => (bool) $membership->is_free_trial,
            'start_date' => $this->dateString($membership->getRawOriginal('start_date')),
            'end_date' => $this->dateString($membership->getRawOriginal('end_date')),
            'contract_start_date' => $membership->contract_start_date,
            'plan' => $plan ? [
                'id' => $plan->id,
                'name' => $plan->name,
                'price' => $plan->price !== null ? (float) $plan->price : null,
                'billing_cycle' => $plan->billing_cycle,
                'commitment_months' => $plan->commitment_months,
                'cancellation_period' => $plan->cancellation_period,
                'cancellation_period_unit' => $plan->cancellation_period_unit,
                'renewal_months' => $plan->renewal_months,
            ] : null,
            'cancellation' => [
                'is_cancelled' => $membership->cancellation_date !== null,
                'effective_date' => $this->dateString($membership->getRawOriginal('cancellation_date')),
                'received_at' => $this->dateTimeString($membership->getRawOriginal('cancellation_received_at')),
                'reason' => $membership->cancellation_reason,
                // Facts computed by the contract logic of the portal itself, so
                // an agent never has to derive a deadline on its own.
                'can_cancel' => (bool) $membership->can_cancel,
                'next_possible_date' => $membership->next_possible_cancellation_date,
                'deadline_for_that_date' => $membership->cancellation_deadline,
                'commitment_end_date' => optional($membership->commitmentEndDate())->toDateString(),
            ],
            'pause' => [
                'is_paused' => $membership->status === 'paused',
                'start_date' => $this->dateString($membership->getRawOriginal('pause_start_date')),
                'end_date' => $this->dateString($membership->getRawOriginal('pause_end_date')),
            ],
            'withdrawal' => [
                'eligible' => (bool) $membership->withdrawal_eligible,
                'deadline' => $membership->withdrawal_deadline,
            ],
        ];
    }

    protected function dateString(mixed $value): ?string
    {
        return $value ? substr((string) $value, 0, 10) : null;
    }

    protected function dateTimeString(mixed $value): ?string
    {
        return $value ? (string) $value : null;
    }
}
