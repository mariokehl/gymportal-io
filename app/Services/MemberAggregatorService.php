<?php

namespace App\Services;

use App\Enums\Aggregator;
use App\Models\Member;
use App\Models\MemberAccessConfig;
use App\Models\MemberAccessLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Stores a member's corporate fitness aggregator account.
 *
 * The data is informational for now: the link stays pending until check-ins
 * through the aggregator are processed, which is not implemented yet.
 */
class MemberAggregatorService
{
    /**
     * Set or replace the member's aggregator account.
     *
     * Changing provider or account id resets the link to pending, saving the
     * same values again keeps an existing link.
     */
    public function assign(Member $member, Aggregator $aggregator, string $accountId, ?User $performedBy): MemberAccessConfig
    {
        return DB::transaction(function () use ($member, $aggregator, $accountId, $performedBy) {
            $config = $member->getOrCreateAccessConfig();

            $previousAggregator = $config->aggregator;
            $previousAccountId = $config->aggregator_account_id;
            $changed = $config->aggregator !== $aggregator
                || $config->aggregator_account_id !== $accountId;

            if (! $changed) {
                return $config;
            }

            $config->aggregator = $aggregator;
            $config->aggregator_account_id = $accountId;
            $config->aggregator_linked_at = null;
            $config->save();

            $this->log($member, MemberAccessLog::ACTION_AGGREGATOR_SET, $performedBy, [
                'aggregator' => $aggregator->value,
                'account_id' => $accountId,
                'previous_aggregator' => $previousAggregator?->value,
                'previous_account_id' => $previousAccountId,
            ]);

            return $config;
        });
    }

    /**
     * Remove the member's aggregator account. Returns false if none was set.
     */
    public function remove(Member $member, ?User $performedBy): bool
    {
        $config = $member->accessConfig;

        if (! $config?->aggregator) {
            return false;
        }

        DB::transaction(function () use ($member, $config, $performedBy) {
            $metadata = [
                'aggregator' => $config->aggregator->value,
                'account_id' => $config->aggregator_account_id,
            ];

            $config->aggregator = null;
            $config->aggregator_account_id = null;
            $config->aggregator_linked_at = null;
            $config->save();

            $this->log($member, MemberAccessLog::ACTION_AGGREGATOR_REMOVED, $performedBy, $metadata);
        });

        return true;
    }

    private function log(Member $member, string $action, ?User $performedBy, array $metadata): void
    {
        MemberAccessLog::create([
            'member_id' => $member->id,
            'action' => $action,
            'performed_by' => $performedBy?->id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => $metadata,
        ]);
    }
}
