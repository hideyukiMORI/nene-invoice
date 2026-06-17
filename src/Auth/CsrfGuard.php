<?php

declare(strict_types=1);

namespace NeneInvoice\Auth;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Stateless double-submit CSRF check for the cookie-authenticated endpoints
 * (`/auth/refresh`, `/auth/logout`) — ADR 0014.
 *
 * The refresh cookie rides automatically on cross-site requests (mitigated, but
 * not eliminated, by `SameSite=Strict`), so these endpoints additionally require
 * the caller to echo the readable `ni_csrf` cookie in an `X-CSRF-Token` header.
 * A forged cross-site request cannot read that cookie to set the header. The
 * `/admin/*` and `/api/*` routes need no such check: they authenticate with a
 * non-ambient `Authorization` bearer that cross-site requests cannot forge.
 */
final class CsrfGuard
{
    public const HEADER = 'X-CSRF-Token';

    /**
     * @throws CsrfValidationException when the header is absent or mismatched
     */
    public static function assert(ServerRequestInterface $request): void
    {
        $cookie = SessionCookies::csrfCookieValue($request);
        $header = $request->getHeaderLine(self::HEADER);

        if ($cookie === null || $header === '' || !hash_equals($cookie, $header)) {
            throw new CsrfValidationException();
        }
    }
}
