<?php

declare(strict_types=1);

namespace NeneInvoice\Auth;

use RuntimeException;

/**
 * The double-submit CSRF check failed on a cookie-authenticated endpoint: the
 * `X-CSRF-Token` header was missing or did not match the `ni_csrf` cookie.
 */
final class CsrfValidationException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('CSRF token missing or invalid.');
    }
}
