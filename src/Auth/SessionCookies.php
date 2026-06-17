<?php

declare(strict_types=1);

namespace NeneInvoice\Auth;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Builds and reads the session cookies for silent re-authentication (ADR 0014).
 *
 * - **Refresh cookie** (`ni_refresh`): `HttpOnly` so JS can never read it,
 *   `Secure`, `SameSite=Strict`, and `Path=/auth` so it is only sent to the auth
 *   endpoints. This is the long-lived credential.
 * - **CSRF cookie** (`ni_csrf`): deliberately readable by JS (no `HttpOnly`) for
 *   the double-submit check; `Secure`, `SameSite=Strict`, `Path=/` so the SPA can
 *   echo it in a header. It is not a credential on its own.
 *
 * `Secure` is always set. Browsers treat `http://localhost` as a secure context,
 * so this holds for local dev as well as production HTTPS.
 */
final class SessionCookies
{
    public const REFRESH_COOKIE = 'ni_refresh';
    public const CSRF_COOKIE = 'ni_csrf';

    private const REFRESH_PATH = '/auth';
    private const CSRF_PATH = '/';

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

    public static function setRefresh(string $rawToken, int $expiresAtTimestamp): string
    {
        return self::build(self::REFRESH_COOKIE, $rawToken, self::REFRESH_PATH, $expiresAtTimestamp, httpOnly: true);
    }

    public static function setCsrf(string $csrfToken, int $expiresAtTimestamp): string
    {
        return self::build(self::CSRF_COOKIE, $csrfToken, self::CSRF_PATH, $expiresAtTimestamp, httpOnly: false);
    }

    public static function clearRefresh(): string
    {
        return self::build(self::REFRESH_COOKIE, '', self::REFRESH_PATH, 0, httpOnly: true);
    }

    public static function clearCsrf(): string
    {
        return self::build(self::CSRF_COOKIE, '', self::CSRF_PATH, 0, httpOnly: false);
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
