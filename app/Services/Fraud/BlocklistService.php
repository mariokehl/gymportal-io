<?php

namespace App\Services\Fraud;

use App\Models\Member;
use App\Models\MemberBlocklist;

class BlocklistService
{
    /**
     * Mitglied zur Sperrliste hinzufügen.
     * Holt IBAN aus der aktiven PaymentMethod des Members.
     */
    public function addMember(
        int     $gymId,
        Member  $member,
        string  $reason,
        ?string $notes = null,
        ?int    $blockedByUserId = null,
    ): MemberBlocklist {
        $n = FraudIdentifierNormalizer::class;

        // IBAN aus der Standard- oder ersten aktiven SEPA PaymentMethod holen
        $iban = $member->defaultPaymentMethod?->iban
            ?? $member->activeSepaPaymentMethod?->iban
            ?? $member->paymentMethods()->whereNotNull('iban')->value('iban');

        return MemberBlocklist::updateOrCreate(
            [
                'gym_id'             => $gymId,
                'original_member_id' => $member->id,
            ],
            [
                'reason'     => $reason,
                'notes'      => $notes,
                'blocked_by' => $blockedByUserId,
                'blocked_at' => now(),
                'blocked_until' => null, // Permanent bis manuell entsperrt

                // Hashes
                'hash_iban' => $iban
                    ? $n::hash($n::normalizeIban($iban))
                    : null,
                'hash_phone' => $member->phone
                    ? $n::hash($n::normalizePhone($member->phone))
                    : null,
                'hash_address' => ($member->address && $member->postal_code)
                    ? $n::hash($n::normalizeAddress($member->address, $member->postal_code, $member->city ?? ''))
                    : null,

                // Verschlüsselt für Levenshtein (Laravel encrypt = AES-256-CBC)
                'encrypted_last_name'  => $member->last_name ? encrypt($member->last_name) : null,
                'encrypted_first_name' => $member->first_name ? encrypt($member->first_name) : null,
                'encrypted_birthdate'  => $member->birth_date
                    ? encrypt($member->birth_date->format('Y-m-d'))
                    : null,
            ]
        );
    }

    /**
     * Manuell zur Sperrliste hinzufügen (ohne verknüpftes Member-Objekt).
     */
    public function addManual(
        int    $gymId,
        array  $data,
        int    $blockedByUserId,
        string $reason = 'manual',
        string $notes = '',
    ): MemberBlocklist {
        $n = FraudIdentifierNormalizer::class;

        $entry = new MemberBlocklist([
            'gym_id'             => $gymId,
            'original_member_id' => $data['member_id'] ?? null,
            'reason'             => $reason,
            'notes'              => $notes,
            'blocked_by'         => $blockedByUserId,
            'blocked_at'         => now(),
            'blocked_until'      => $data['blocked_until'] ?? null,
        ]);

        if (!empty($data['iban'])) {
            $entry->hash_iban = $n::hash($n::normalizeIban($data['iban']));
        }
        if (!empty($data['phone'])) {
            $entry->hash_phone = $n::hash($n::normalizePhone($data['phone']));
        }
        if (!empty($data['address']) && !empty($data['postal_code'])) {
            $entry->hash_address = $n::hash(
                $n::normalizeAddress($data['address'], $data['postal_code'], $data['city'] ?? '')
            );
        }
        if (!empty($data['last_name'])) {
            $entry->encrypted_last_name = encrypt($data['last_name']);
        }
        if (!empty($data['first_name'])) {
            $entry->encrypted_first_name = encrypt($data['first_name']);
        }
        if (!empty($data['birth_date'])) {
            $entry->encrypted_birthdate = encrypt($data['birth_date']);
        }

        $entry->save();
        return $entry;
    }

    /**
     * Sperre aufheben (mit Pflichtbegründung).
     */
    public function unblock(MemberBlocklist $entry, int $unlockedByUserId, string $reason): void
    {
        $entry->update([
            'blocked_until' => now(),
            'notes' => $entry->notes
                . "\n\n[Entsperrt " . now()->format('d.m.Y H:i')
                . " von User #{$unlockedByUserId}]: {$reason}",
        ]);

        // Mitglied-Status zurücksetzen falls verknüpft
        if ($entry->original_member_id && $entry->member) {
            $entry->member->update(['status' => 'active']);
        }
    }
}
