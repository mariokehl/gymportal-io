<?php

namespace App\Mail\Policies;

use App\Mail\Contracts\MemberMail;
use App\Models\Member;

/**
 * Lehnt Versand ab, wenn das Mitglied gar keine E-Mail-Adresse hat.
 */
final class MissingEmailPolicy implements MemberMailPolicy
{
    public function name(): string
    {
        return 'missing_email';
    }

    public function check(Member $member, MemberMail $mail): PolicyDecision
    {
        if ($member->email) {
            return PolicyDecision::allow();
        }

        return PolicyDecision::deny(
            'Mitglied hat keine E-Mail-Adresse hinterlegt.',
            'MEMBER_NO_EMAIL',
        );
    }
}
