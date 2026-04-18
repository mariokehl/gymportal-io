<?php

namespace App\Mail\Dispatching;

use App\Mail\Contracts\MemberMail;
use App\Mail\Policies\MemberMailPolicy;
use App\Models\Member;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Zentraler Versandpunkt für alle {@see MemberMail}-Mailables.
 *
 * Verantwortlich für:
 *   - Auswerten aller registrierten {@see MemberMailPolicy} (Polymorphismus über Liste)
 *   - Einheitliches Logging (skip/sent/failed) mit vollem Kontext
 *   - Exception-Kapselung, damit Aufrufer nicht `try/catch` duplizieren müssen
 *   - Rückgabe eines {@see MailDispatchResult}, damit Aufrufer ggf.
 *     Fehlerantworten an den Client generieren können.
 *
 * Neue Versand-Regeln werden als neue Policy-Klasse ergänzt — der Dispatcher
 * selbst bleibt unverändert (Open/Closed).
 */
final class MemberMailDispatcher
{
    /**
     * @param  iterable<MemberMailPolicy>  $policies
     */
    public function __construct(
        private readonly iterable $policies,
    ) {
    }

    /**
     * Versendet an die im Member hinterlegte Adresse.
     */
    public function sendToMember(Member $member, MemberMail $mail): MailDispatchResult
    {
        return $this->dispatch($member, $mail, $member->email);
    }

    /**
     * Versendet an eine explizit übergebene Adresse (z. B. aus einem
     * Widerrufs-Formular). Die Policies prüfen weiterhin das Member-
     * Profil; zusätzlich wird die Zieladresse selbst auf Synthetik geprüft.
     */
    public function sendToAddress(Member $member, MemberMail $mail, ?string $email): MailDispatchResult
    {
        return $this->dispatch($member, $mail, $email);
    }

    private function dispatch(Member $member, MemberMail $mail, ?string $email): MailDispatchResult
    {
        $mailName = $mail::class;
        $baseContext = [
            'member_id' => $member->id,
            'mail' => $mailName,
        ];

        if (!$email) {
            return $this->skip(
                'missing_address',
                'Kein Empfänger angegeben.',
                'MISSING_ADDRESS',
                $baseContext,
            );
        }

        if (Member::isSyntheticEmail($email)) {
            return $this->skip(
                'synthetic_address',
                'Empfänger-Adresse ist synthetisch (Import-Platzhalter).',
                'MEMBER_SYNTHETIC_EMAIL',
                $baseContext,
            );
        }

        foreach ($this->policies as $policy) {
            $decision = $policy->check($member, $mail);

            if (!$decision->allowed) {
                return $this->skip(
                    $policy->name(),
                    $decision->reason ?? 'Policy hat Versand abgelehnt.',
                    $decision->errorCode,
                    $baseContext,
                );
            }
        }

        try {
            Mail::to($email)->send($mail);
        } catch (Throwable $e) {
            Log::error('Member mail failed', $baseContext + [
                'error' => $e->getMessage(),
            ]);

            return MailDispatchResult::failed($e->getMessage());
        }

        Log::info('Member mail sent', $baseContext);

        return MailDispatchResult::sent();
    }

    private function skip(
        string $policy,
        string $reason,
        ?string $errorCode,
        array $context,
    ): MailDispatchResult {
        Log::info('Member mail skipped', $context + [
            'policy' => $policy,
            'reason' => $reason,
        ]);

        return MailDispatchResult::skipped($reason, $errorCode);
    }
}
