<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\MemberAccessConfig;
use App\Models\MemberAccessLog;
use App\Mail\MemberAppAccessLink;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class MemberAccessController extends Controller
{
    use AuthorizesRequests;

    /**
     * Update member access configuration
     */
    public function update(Request $request, Member $member)
    {
        $this->authorize('update', $member);

        $validated = $request->validate([
            'qr_code_enabled' => 'boolean',
            'nfc_enabled' => 'boolean',
            'nfc_uid' => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) use ($member) {
                    if ($value) {
                        // Normalisiere die NFC-ID
                        $normalized = $this->normalizeCardId($value);
                        if (!$normalized) {
                            $fail('Die NFC-ID hat ein ungültiges Format.');
                            return;
                        }

                        // Prüfe auf Eindeutigkeit
                        $exists = MemberAccessConfig::where('nfc_uid', $normalized)
                            ->where('member_id', '!=', $member->id)
                            ->exists();

                        if ($exists) {
                            $fail('Diese NFC-ID ist bereits einem anderen Mitglied zugeordnet.');
                        }
                    }
                }
            ],
            'solarium_enabled' => 'boolean',
            'solarium_minutes' => 'nullable|integer|min:0',
            'vending_enabled' => 'boolean',
            'vending_credit' => 'nullable|numeric|min:0',
            'massage_enabled' => 'boolean',
            'massage_sessions' => 'nullable|integer|min:0',
            'coffee_flat_enabled' => 'boolean',
            'coffee_flat_expiry' => 'nullable|date|after:today',
        ]);

        // Normalisiere NFC-UID vor dem Speichern
        if (isset($validated['nfc_uid']) && $validated['nfc_uid']) {
            $validated['nfc_uid'] = $this->normalizeCardId($validated['nfc_uid']);
        }

        DB::transaction(function () use ($member, $validated) {
            $config = MemberAccessConfig::updateOrCreate(
                ['member_id' => $member->id],
                $validated
            );

            // Log die Änderung
            $this->logAccessConfigChange($member, $config, auth()->user());
        });

        return back()->with('success', 'Zugangskonfiguration wurde aktualisiert.');
    }

    /**
     * Invalidate QR code for a member
     */
    public function invalidateQr(Member $member)
    {
        $this->authorize('update', $member);

        $config = MemberAccessConfig::firstOrCreate(
            ['member_id' => $member->id],
            ['qr_code_enabled' => false]
        );

        $config->update([
            'qr_code_enabled' => false,
            'qr_code_invalidated_at' => now(),
            'qr_code_invalidated_by' => auth()->id(),
        ]);

        // Log die Aktion
        MemberAccessLog::create([
            'member_id' => $member->id,
            'action' => 'qr_invalidated',
            'performed_by' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => [
                'reason' => request()->input('reason', 'Admin-Aktion'),
            ],
        ]);

        return back()->with('success', 'QR-Code wurde invalidiert.');
    }

    /**
     * Send app access link to member via email
     */
    public function sendAppLink(Member $member)
    {
        $this->authorize('update', $member);

        // Erstelle die App-URL
        $appUrl = config('app.pwa_url', 'https://members.gymportal.io');
        $gymSlug = $member->gym->slug;
        $loginUrl = "{$appUrl}/{$gymSlug}/login";

        // Sende E-Mail
        Mail::to($member->email)->send(new MemberAppAccessLink($member, $loginUrl));

        // Log die Aktion
        MemberAccessLog::create([
            'member_id' => $member->id,
            'action' => 'app_link_sent',
            'performed_by' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => [
                'email' => $member->email,
                'token_expires_at' => now()->addHours(24),
            ],
        ]);

        return back()->with('success', 'App-Link wurde per E-Mail versendet.');
    }

    /**
     * Get access logs for a member
     */
    public function logs(Member $member)
    {
        $this->authorize('view', $member);

        $logs = MemberAccessLog::where('member_id', $member->id)
            ->with('performedBy')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        if (request()->wantsJson()) {
            return response()->json($logs);
        }

        return Inertia::render('Members/AccessLogs', [
            'member' => $member->load('gym'),
            'logs' => $logs,
        ]);
    }

    /**
     * Validate access (API endpoint for scanner devices)
     */
    public function validateAccess(Request $request)
    {
        $validated = $request->validate([
            'method' => 'required|in:qr,nfc',
            'identifier' => 'required|string',
            'gym_id' => 'required|exists:gyms,id',
            'device_id' => 'nullable|string',
            'service' => 'nullable|string|in:gym,solarium,vending,massage,coffee',
        ]);

        $service = $validated['service'] ?? 'gym';
        $success = false;
        $member = null;
        $message = 'Zugang verweigert';

        try {
            if ($validated['method'] === 'qr') {
                // QR-Code Validierung
                $member = $this->validateQrCode($validated['identifier'], $validated['gym_id']);
            } else {
                // NFC Validierung
                $normalizedUid = $this->normalizeCardId($validated['identifier']);
                if ($normalizedUid) {
                    $member = $this->validateNfcUid($normalizedUid, $validated['gym_id']);
                }
            }

            if ($member) {
                // Prüfe Service-Berechtigung
                $success = $this->checkServiceAccess($member, $service);
                $message = $success ? 'Zugang gewährt' : 'Keine Berechtigung für diesen Service';
            }
        } catch (\Exception $e) {
            $message = $e->getMessage();
        }

        // Log den Zugangsversuch
        if ($member) {
            MemberAccessLog::create([
                'member_id' => $member->id,
                'action' => 'access_attempt',
                'service' => $service,
                'method' => $validated['method'],
                'success' => $success,
                'device_id' => $validated['device_id'] ?? null,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'metadata' => [
                    'message' => $message,
                    'identifier' => substr($validated['identifier'], 0, 4) . '***',
                ],
            ]);
        }

        return response()->json([
            'success' => $success,
            'message' => $message,
            'member' => $success && $member ? [
                'id' => $member->id,
                'name' => $member->full_name,
                'photo' => $member->profile_photo_path,
                'status' => $member->status,
            ] : null,
        ], $success ? 200 : 403);
    }

    /**
     * Normalisiere verschiedene Karten-ID Formate
     */
    private function normalizeCardId($cardId)
    {
        if (!$cardId) return null;

        // Whitespace entfernen und in Großbuchstaben
        $cardId = strtoupper(trim($cardId));

        // 1. UID-Format mit Trennzeichen (04:A1:B2:C3 oder 04-A1-B2-C3)
        if (strpos($cardId, ':') !== false || strpos($cardId, '-') !== false) {
            $normalized = preg_replace('/[:-]/', '', $cardId);
            if (preg_match('/^[0-9A-F]+$/', $normalized)) {
                return $normalized;
            }
        }

        // 2. Hexadezimal mit 0x Prefix
        elseif (strpos($cardId, '0X') === 0) {
            $hexPart = substr($cardId, 2);
            if (preg_match('/^[0-9A-F]+$/', $hexPart)) {
                return $hexPart;
            }
        }

        // 3. Reines Hexadezimal (nur A-F, 0-9)
        elseif (preg_match('/^[0-9A-F]+$/', $cardId)) {
            return $cardId;
        }

        // 4. Reine Dezimalzahl
        elseif (preg_match('/^[0-9]+$/', $cardId)) {
            // Dezimal zu Hex konvertieren
            return strtoupper(dechex(intval($cardId)));
        }

        return null;
    }

    /**
     * Validate QR code
     */
    private function validateQrCode($identifier, $gymId)
    {
        // QR-Code Format: {member_number}:{timestamp}:{hash}
        $parts = explode(':', $identifier);
        if (count($parts) !== 3) {
            throw new \Exception('Ungültiges QR-Code Format');
        }

        [$memberNumber, $timestamp, $hash] = $parts;

        // Prüfe Zeitstempel (max. 30 Sekunden alt)
        if (abs(time() - intval($timestamp)) > 30) {
            throw new \Exception('QR-Code ist abgelaufen');
        }

        $member = Member::where('member_number', $memberNumber)
            ->where('gym_id', $gymId)
            ->first();

        if (!$member) {
            throw new \Exception('Mitglied nicht gefunden');
        }

        // Validiere Hash
        $gym = $member->gym;
        if (!$gym->validateHash($memberNumber, $timestamp, $hash)) {
            throw new \Exception('Ungültiger QR-Code');
        }

        // Prüfe ob QR-Code aktiviert ist
        $config = $member->accessConfig;
        if (!$config || !$config->qr_code_enabled) {
            throw new \Exception('QR-Code Zugang ist deaktiviert');
        }

        return $member;
    }

    /**
     * Validate NFC UID
     */
    private function validateNfcUid($uid, $gymId)
    {
        $config = MemberAccessConfig::where('nfc_uid', $uid)
            ->whereHas('member', function ($query) use ($gymId) {
                $query->where('gym_id', $gymId);
            })
            ->with('member')
            ->first();

        if (!$config) {
            throw new \Exception('NFC-Tag nicht registriert');
        }

        if (!$config->nfc_enabled) {
            throw new \Exception('NFC-Zugang ist deaktiviert');
        }

        return $config->member;
    }

    /**
     * Check if member has access to specific service
     */
    private function checkServiceAccess(Member $member, $service)
    {
        // Prüfe Mitgliedsstatus
        if ($member->status !== 'active') {
            return false;
        }

        // Prüfe aktive Mitgliedschaft
        if (!$member->activeMembership()) {
            return false;
        }

        $config = $member->accessConfig;
        if (!$config) {
            return $service === 'gym'; // Nur Gym-Zugang ohne Config
        }

        switch ($service) {
            case 'gym':
                return true;

            case 'solarium':
                return $config->solarium_enabled && $config->solarium_minutes > 0;

            case 'vending':
                return $config->vending_enabled && $config->vending_credit > 0;

            case 'massage':
                return $config->massage_enabled && $config->massage_sessions > 0;

            case 'coffee':
                return $config->coffee_flat_enabled &&
                       (!$config->coffee_flat_expiry || $config->coffee_flat_expiry->isFuture());

            default:
                return false;
        }
    }

    /**
     * Log access configuration changes
     */
    private function logAccessConfigChange(Member $member, MemberAccessConfig $config, $user)
    {
        $changes = $config->getChanges();
        if (empty($changes)) {
            return;
        }

        MemberAccessLog::create([
            'member_id' => $member->id,
            'action' => 'config_updated',
            'performed_by' => $user->id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => [
                'changes' => $changes,
                'old_values' => $config->getOriginal(),
            ],
        ]);
    }

    /**
     * Consume service credit (for vending, solarium, etc.)
     */
    public function consumeCredit(Request $request, Member $member)
    {
        $this->authorize('update', $member);

        $validated = $request->validate([
            'service' => 'required|in:solarium,vending,massage',
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string|max:255',
        ]);

        $config = $member->accessConfig;
        if (!$config) {
            return response()->json(['error' => 'Keine Zugangskonfiguration vorhanden'], 404);
        }

        DB::transaction(function () use ($config, $validated, $member) {
            switch ($validated['service']) {
                case 'solarium':
                    if ($config->solarium_minutes < $validated['amount']) {
                        throw new \Exception('Nicht genügend Minuten verfügbar');
                    }
                    $config->decrement('solarium_minutes', $validated['amount']);
                    break;

                case 'vending':
                    if ($config->vending_credit < $validated['amount']) {
                        throw new \Exception('Nicht genügend Guthaben verfügbar');
                    }
                    $config->decrement('vending_credit', $validated['amount']);
                    break;

                case 'massage':
                    if ($config->massage_sessions < $validated['amount']) {
                        throw new \Exception('Nicht genügend Sitzungen verfügbar');
                    }
                    $config->decrement('massage_sessions', $validated['amount']);
                    break;
            }

            // Log den Verbrauch
            MemberAccessLog::create([
                'member_id' => $member->id,
                'action' => 'credit_consumed',
                'service' => $validated['service'],
                'performed_by' => auth()->id(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'metadata' => [
                    'amount' => $validated['amount'],
                    'description' => $validated['description'] ?? null,
                    'remaining' => $config->fresh()->toArray(),
                ],
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Guthaben wurde verbucht',
            'remaining' => $config->fresh()->only([
                'solarium_minutes',
                'vending_credit',
                'massage_sessions',
            ]),
        ]);
    }
}
