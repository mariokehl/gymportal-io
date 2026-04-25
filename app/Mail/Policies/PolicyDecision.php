<?php

namespace App\Mail\Policies;

/**
 * Entscheidung einer einzelnen Policy.
 * Immutable Value Object.
 */
final class PolicyDecision
{
    private function __construct(
        public readonly bool $allowed,
        public readonly ?string $reason = null,
        public readonly ?string $errorCode = null,
    ) {
    }

    public static function allow(): self
    {
        return new self(true);
    }

    public static function deny(string $reason, ?string $errorCode = null): self
    {
        return new self(false, $reason, $errorCode);
    }
}
