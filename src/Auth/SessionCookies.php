<?php

declare(strict_types=1);

namespace NeneInvoice\Auth;

use NeneInvoice\Http\BasePath;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Builds and reads the session cookies for silent re-authentication (ADR 0014).
 *
 * - **Refresh cookie** (`ni_refresh`): `HttpOnly` so JS can never read it,
 *   `Secure`, `SameSite=Strict`, and `Path=<base>/auth` so it is only sent to the
 *   auth endpoints. This is the long-lived credential.
 * - **CSRF cookie** (`ni_csrf`): deliberately readable by JS (no `HttpOnly`) for
 *   the double-submit check; `Secure`, `SameSite=Strict`, `Path=<base>/` so the
 *   SPA can echo it in a header. It is not a credential on its own.
 *
 * The cookie `Path` is **base-relative** (ADR 0015): under a subdirectory install
 * the refresh cookie must scope to `/invoice/auth` (so the browser sends it to
 * `/invoice/auth/refresh`), and the CSRF cookie to `/invoice/` (not the whole
 * domain, which would leak it to a sibling site at `/`). At the document root the
 * base is `''`, giving the original `/auth` and `/`. Callers pass the detected
 * base via {@see BasePath::fromRequest()}.
 *
 * `Secure` is always set. Browsers treat `http://localhost` as a secure context,
 * so this holds for local dev as well as production HTTPS.
 */
final class SessionCookies
{
    public const REFRESH_COOKIE = 'ni_refresh';
    public const CSRF_COOKIE = 'ni_csrf';

    public static function refreshToken(ServerRequestInterface $request): ?string
    {
        $value = $request->getCookieParams()[self::REFRESH_COOKIE] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    public static function csrfCookieValue(ServerRequestInterface $request): ?string
    {
        $value = $request->getCookieParams()[self::CSRF_COOKIE] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    public static function setRefresh(string $rawToken, int $expiresAtTimestamp, string $basePath = ''): string
    {
        return self::build(self::REFRESH_COOKIE, $rawToken, self::refreshPath($basePath), $expiresAtTimestamp, httpOnly: true);
    }

    public static function setCsrf(string $csrfToken, int $expiresAtTimestamp, string $basePath = ''): string
    {
        return self::build(self::CSRF_COOKIE, $csrfToken, self::csrfPath($basePath), $expiresAtTimestamp, httpOnly: false);
    }

    public static function clearRefresh(string $basePath = ''): string
    {
        return self::build(self::REFRESH_COOKIE, '', self::refreshPath($basePath), 0, httpOnly: true);
    }

    public static function clearCsrf(string $basePath = ''): string
    {
        return self::build(self::CSRF_COOKIE, '', self::csrfPath($basePath), 0, httpOnly: false);
    }

    private static function refreshPath(string $basePath): string
    {
        return $basePath . '/auth';
    }

    private static function csrfPath(string $basePath): string
    {
        return $basePath . '/';
    }

    private static function build(string $name, string $value, string $path, int $expiresAtTimestamp, bool $httpOnly): string
    {
        // Expired (timestamp 0) → clear with an epoch expiry and Max-Age=0.
        $expiry = $value === ''
            ? 'Expires=Thu, 01 Jan 1970 00:00:00 GMT; Max-Age=0'
            : 'Expires=' . gmdate('D, d M Y H:i:s', $expiresAtTimestamp) . ' GMT';

        $attributes = [
            $name . '=' . $value,
            'Path=' . $path,
            $expiry,
            'Secure',
            'SameSite=Strict',
        ];

        if ($httpOnly) {
            $attributes[] = 'HttpOnly';
        }

        return implode('; ', $attributes);
    }
}
