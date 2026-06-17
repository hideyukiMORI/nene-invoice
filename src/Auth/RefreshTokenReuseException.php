<?php

declare(strict_types=1);

namespace NeneInvoice\Auth;

use RuntimeException;

/**
 * An already-rotated (or revoked) refresh token was presented — the hallmark of
 * a stolen-token replay. The whole token family has been revoked as a result;
 * the caller fails closed exactly like {@see InvalidRefreshTokenException}, but
 * the distinct type lets logging/audit flag the replay.
 */
final class RefreshTokenReuseException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The refresh token has already been used; the session family was revoked.');
    }
}
