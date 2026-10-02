<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MemberAccessConfig;
use App\Models\MemberAccessLog;
use App\Models\MemberNfcTag;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * NFC tags of a member: the primary tag in member_access_configs.nfc_uid plus
 * any number of additional tags in member_nfc_tags.
 *
 * Lookups always check the primary column first, so the common scan stays a
 * single indexed query; the additional tags are only consulted on a miss.
 */
class NfcTagService
{
    /**
     * Normalise the supported card id formats to plain uppercase hex.
     */
    public function normalizeUid(?string $cardId): ?string
    {
        if (! $cardId) {
            return null;
        }

        $cardId = strtoupper(trim($cardId));

        // 1. UID with separators (04:A1:B2:C3 or 04-A1-B2-C3)
        if (str_contains($cardId, ':') || str_contains($cardId, '-')) {
            $normalized = preg_replace('/[:-]/', '', $cardId);
            if (preg_match('/^[0-9A-F]+$/', $normalized)) {
                return $normalized;
            }
        }

        // 2. Hex with 0x prefix
        elseif (str_starts_with($cardId, '0X')) {
            $hexPart = substr($cardId, 2);
            if (preg_match('/^[0-9A-F]+$/', $hexPart)) {
                return $hexPart;
            }
        }

        // 3. Plain hex (A-F, 0-9)
        elseif (preg_match('/^[0-9A-F]+$/', $cardId)) {
            return $cardId;
        }

        // 4. Plain decimal
        elseif (preg_match('/^[0-9]+$/', $cardId)) {
            return strtoupper(dechex(intval($cardId)));
        }

        return null;
    }

    /**
     * Find the access configuration a tag belongs to, limited to members of
     * the given gyms. Returns the configuration regardless of nfc_enabled so
     * callers can tell a disabled tag from an unknown one.
     *
     * @param  array<int>  $gymIds
     */
    public function findConfigByUid(string $uid, array $gymIds): ?MemberAccessConfig
    {
        $inGyms = fn ($query) => $query->whereIn('gym_id', $gymIds);

        $config = MemberAccessConfig::where('nfc_uid', $uid)
            ->whereHas('member', $inGyms)
            ->first();

        if ($config) {
            return $config;
        }

        $configId = MemberNfcTag::where('uid', $uid)->value('member_access_config_id');

        if ($configId === null) {
            return null;
        }

        return MemberAccessConfig::whereKey($configId)
            ->whereHas('member', $inGyms)
            ->first();
    }

    /**
     * Check whether a tag is already assigned anywhere in the installation.
     *
     * Passing a member ignores that member's own primary tag, so saving the
     * primary tag unchanged does not collide with itself.
     */
    public function isTaken(string $uid, ?Member $ignorePrimaryOf = null): bool
    {
        $primaryTaken = MemberAccessConfig::where('nfc_uid', $uid)
            ->when($ignorePrimaryOf, fn ($query) => $query->where('member_id', '!=', $ignorePrimaryOf->id))
            ->exists();

        return $primaryTaken || MemberNfcTag::where('uid', $uid)->exists();
    }

    /**
     * Add an additional tag to the member.
     */
    public function add(Member $member, string $uid, ?User $performedBy): MemberNfcTag
    {
        return DB::transaction(function () use ($member, $uid, $performedBy) {
            $config = $member->getOrCreateAccessConfig();

            $tag = $config->additionalNfcTags()->create([
                'uid' => $uid,
                'registered_at' => now(),
            ]);

            $this->log($member, MemberAccessLog::ACTION_NFC_REGISTERED, $performedBy, [
                'nfc_uid' => $uid,
                'additional' => true,
            ]);

            return $tag;
        });
    }

    /**
     * Remove an additional tag. Turns NFC access off once the member has no
     * tag left at all.
     */
    public function remove(Member $member, MemberNfcTag $tag, ?User $performedBy): void
    {
        DB::transaction(function () use ($member, $tag, $performedBy) {
            $config = $tag->accessConfig;
            $tag->delete();

            $this->log($member, MemberAccessLog::ACTION_NFC_REMOVED, $performedBy, [
                'nfc_uid' => $tag->uid,
                'additional' => true,
            ]);

            $this->disableWithoutTags($config);
        });
    }

    /**
     * Keep the configuration consistent after its primary tag was cleared:
     * the oldest additional tag moves up to become the primary one, and
     * without any tag left NFC access is turned off.
     */
    public function handlePrimaryRemoved(MemberAccessConfig $config): void
    {
        if (! empty($config->nfc_uid)) {
            return;
        }

        $next = $config->additionalNfcTags()->first();

        if (! $next) {
            $this->disableWithoutTags($config);

            return;
        }

        $next->delete();
        $config->update([
            'nfc_uid' => $next->uid,
            'nfc_registered_at' => $next->registered_at ?? now(),
        ]);
    }

    private function disableWithoutTags(MemberAccessConfig $config): void
    {
        if (empty($config->nfc_uid) && ! $config->additionalNfcTags()->exists()) {
            $config->update(['nfc_enabled' => false]);
        }
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
