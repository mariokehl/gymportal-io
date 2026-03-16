<?php

namespace App\Dto;

class FraudCheckResult
{
    public function __construct(
        public readonly string $action, // 'allowed' | 'flagged' | 'blocked'
        public readonly int    $score,  // 0–100
        public readonly array  $matched, // Felder die getroffen haben
        public readonly ?int   $fraudCheckId = null, // ID des erstellten FraudCheck-Records
    ) {}

    public function isBlocked(): bool
    {
        return $this->action === 'blocked';
    }

    public function isFlagged(): bool
    {
        return $this->action === 'flagged';
    }

    public function isAllowed(): bool
    {
        return $this->action === 'allowed';
    }
}
