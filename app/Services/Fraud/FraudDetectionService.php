<?php

namespace App\Services\Fraud;

use App\Dto\FraudCheckResult;
use App\Models\FraudCheck;
use App\Models\MemberBlocklist;

class FraudDetectionService
{
    /** Score-Gewichtung pro Identifier */
    const FIELD_SCORES = [
        'iban'    => 100,
        'phone'   => 80,
        'name'    => 70,
        'address' => 40,
    ];

    /**
     * Registrierungsdaten gegen Sperrliste prüfen.
     *
     * Erwartet im $data-Array die Widget-Registrierungsdaten:
     *   first_name, last_name, email, phone, birth_date,
     *   address, postal_code, city, iban
     */
    public function checkRegistration(int $gymId, array $data): FraudCheckResult
    {
        $blockThreshold = (int) config('fraud.block_threshold', 80);
        $flagThreshold  = (int) config('fraud.flag_threshold', 40);
        $levenshteinMax = (int) config('fraud.name_levenshtein_max', 2);

        // 1. Hashes aus den Eingabedaten bauen
        $hashes = $this->buildHashes($data);

        $matched    = [];
        $totalScore = 0;
        $matchedEntryId = null;

        // 2. Aktive Sperrlisten-Einträge für dieses Gym laden
        $activeEntries = MemberBlocklist::where('gym_id', $gymId)
            ->active()
            ->get();

        foreach ($activeEntries as $entry) {
            $entryMatches = [];

            // Exakte Hash-Vergleiche: IBAN, Phone, Address
            foreach (['iban', 'phone', 'address'] as $field) {
                if (
                    isset($hashes[$field]) &&
                    $entry->{"hash_{$field}"} &&
                    hash_equals($entry->{"hash_{$field}"}, $hashes[$field])
                ) {
                    $entryMatches[$field] = self::FIELD_SCORES[$field];
                }
            }

            // Fuzzy-Match: Nachname + Vorname via Levenshtein
            if (!empty($data['last_name']) && $entry->encrypted_last_name) {
                $storedLastName = FraudIdentifierNormalizer::normalizeName(decrypt($entry->encrypted_last_name));
                $inputLastName  = FraudIdentifierNormalizer::normalizeName($data['last_name']);

                $distance = levenshtein($inputLastName, $storedLastName);

                if ($distance <= $levenshteinMax) {
                    // Zusätzliche Prüfung: Geburtsdatum ODER exakter Vorname
                    $confirmed = false;

                    // Geburtsdatum prüfen
                    if (!empty($data['birth_date']) && $entry->encrypted_birthdate) {
                        $storedBirthdate = decrypt($entry->encrypted_birthdate);
                        $inputBirthdate  = $data['birth_date'] instanceof \Carbon\Carbon
                            ? $data['birth_date']->format('Y-m-d')
                            : (string) $data['birth_date'];

                        $confirmed = ($inputBirthdate === $storedBirthdate);
                    }

                    // Vorname als zusätzliche Bestätigung
                    if (!$confirmed && !empty($data['first_name']) && $entry->encrypted_first_name) {
                        $storedFirstName = FraudIdentifierNormalizer::normalizeName(decrypt($entry->encrypted_first_name));
                        $inputFirstName  = FraudIdentifierNormalizer::normalizeName($data['first_name']);
                        $firstNameDistance = levenshtein($inputFirstName, $storedFirstName);

                        $confirmed = ($firstNameDistance <= 1);
                    }

                    // Exakter Nachname-Treffer (distance=0) braucht keine zusätzliche Bestätigung
                    if ($confirmed || $distance === 0) {
                        $entryMatches['name'] = [
                            'score'    => self::FIELD_SCORES['name'],
                            'distance' => $distance,
                        ];
                    }
                }
            }

            // Besten Match für diesen Entry berechnen
            if (!empty($entryMatches)) {
                $entryScore = max(array_map(
                    fn ($v) => is_array($v) ? $v['score'] : $v,
                    $entryMatches
                ));

                // Kombinations-Bonus
                if (count($entryMatches) >= 2) {
                    $entryScore = min(100, $entryScore + 20);
                    $entryMatches['_combination_bonus'] = 20;
                }

                // Höchsten Score über alle Entries behalten
                if ($entryScore > $totalScore) {
                    $totalScore     = $entryScore;
                    $matched        = $entryMatches;
                    $matchedEntryId = $entry->id;
                }
            }
        }

        // 3. Aktion bestimmen
        $action = match (true) {
            $totalScore >= $blockThreshold => 'blocked',
            $totalScore >= $flagThreshold  => 'flagged',
            default                        => 'allowed',
        };

        // 4. Audit-Log schreiben
        $fraudCheck = FraudCheck::create([
            'gym_id'             => $gymId,
            'blocklist_entry_id' => $matchedEntryId,
            'fraud_score'        => $totalScore,
            'matched_fields'     => $matched,
            'action'             => $action,
            'email'              => $data['email'] ?? '',
            'ip_address'         => request()->ip(),
            'checked_at'         => now(),
        ]);

        return new FraudCheckResult($action, $totalScore, $matched, $fraudCheck->id);
    }

    private function buildHashes(array $data): array
    {
        $hashes = [];

        if (!empty($data['iban'])) {
            $hashes['iban'] = FraudIdentifierNormalizer::hash(
                FraudIdentifierNormalizer::normalizeIban($data['iban'])
            );
        }

        if (!empty($data['phone'])) {
            $hashes['phone'] = FraudIdentifierNormalizer::hash(
                FraudIdentifierNormalizer::normalizePhone($data['phone'])
            );
        }

        if (!empty($data['address']) && !empty($data['postal_code'])) {
            $hashes['address'] = FraudIdentifierNormalizer::hash(
                FraudIdentifierNormalizer::normalizeAddress(
                    $data['address'],
                    $data['postal_code'],
                    $data['city'] ?? ''
                )
            );
        }

        return $hashes;
    }
}
