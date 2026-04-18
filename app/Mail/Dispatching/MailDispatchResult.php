<?php

namespace App\Mail\Dispatching;

/**
 * Ergebnis eines Versandversuchs über den {@see MemberMailDispatcher}.
 * Immutable Value Object, kapselt Status + menschenlesbaren Grund + Error-Code.
 *
 * Drei mögliche Endzustände:
 *   - sent:    Mail wurde übergeben (sync send erfolgreich)
 *   - skipped: Eine Policy hat den Versand verweigert
 *   - failed:  Übergabe hat eine Exception geworfen
 */
final class MailDispatchResult
{
    private function __construct(
        public readonly string $status,
        public readonly ?string $reason = null,
        public readonly ?string $errorCode = null,
    ) {
    }

    public static function sent(): self
    {
        return new self('sent');
    }

    public static function skipped(string $reason, ?string $errorCode = null): self
    {
        return new self('skipped', $reason, $errorCode);
    }

    public static function failed(string $reason): self
    {
        return new self('failed', $reason);
    }

    public function wasSent(): bool
    {
        return $this->status === 'sent';
    }

    public function wasSkipped(): bool
    {
        return $this->status === 'skipped';
    }

    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }
}
