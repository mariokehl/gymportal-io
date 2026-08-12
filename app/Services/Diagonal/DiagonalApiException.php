<?php

namespace App\Services\Diagonal;

use RuntimeException;
use Throwable;

/**
 * Raised when the DIAGONAL Inkasso API cannot be reached or rejects a request.
 */
class DiagonalApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $status = null,
        public readonly ?string $errorCode = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
