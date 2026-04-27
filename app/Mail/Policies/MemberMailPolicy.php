<?php

namespace App\Mail\Policies;

use App\Mail\Contracts\MemberMail;
use App\Models\Member;

/**
 * Policy, die prüft, ob eine {@see MemberMail} an ein {@see Member} versendet
 * werden darf. Konkrete Implementierungen fokussieren auf genau eine Regel
 * (SRP) — der Dispatcher iteriert über alle registrierten Policies.
 */
interface MemberMailPolicy
{
    /**
     * Kurzer, stabiler Identifier für Logging/Fehlerantworten
     * (z. B. "synthetic_email", "missing_email", "opt_out").
     */
    public function name(): string;

    public function check(Member $member, MemberMail $mail): PolicyDecision;
}
