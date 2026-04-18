<?php

namespace App\Mail\Policies;

use App\Mail\Contracts\MemberMail;
use App\Models\Member;

/**
 * Lehnt Versand ab, wenn die E-Mail eine synthetische Import-Platzhalter-
 * Adresse ist (siehe {@see Member::SYNTHETIC_EMAIL_DOMAIN}).
 */
final class SyntheticEmailPolicy implements MemberMailPolicy
{
    public function name(): string
    {
        return 'synthetic_email';
    }

    public function check(Member $member, MemberMail $mail): PolicyDecision
    {
        if (!Member::isSyntheticEmail($member->email)) {
            return PolicyDecision::allow();
        }

        return PolicyDecision::deny(
            'Mitglied hat eine synthetische Import-E-Mail und darf nicht angeschrieben werden.',
            'MEMBER_SYNTHETIC_EMAIL',
        );
    }
}
