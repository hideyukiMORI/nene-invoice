<?php

declare(strict_types=1);

namespace NeneInvoice\Auth;

use RuntimeException;

/**
 * Thrown when an IP exceeds the allowed number of failed login attempts within
 * the throttling window (security: diagnostic F-2). Maps to HTTP 429.
 */
final class TooManyLoginAttemptsException extends RuntimeException
{
    public function __construct(public readonly int $retryAfterSeconds)
    {
        parent::__construct('Too many failed login attempts. Try again later.');
    }
}
