<?php

declare(strict_types=1);

namespace NeneInvoice\Auth;

use RuntimeException;

/**
 * The presented refresh token is missing, unknown, expired, or its principal is
 * no longer eligible (deactivated / moved tenant). The session fails closed:
 * the caller clears the cookie and returns 401.
 */
final class InvalidRefreshTokenException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The refresh token is missing, expired, or invalid.');
    }
}
