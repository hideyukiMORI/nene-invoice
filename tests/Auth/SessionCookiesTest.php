<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Auth;

use NeneInvoice\Auth\SessionCookies;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

final class SessionCookiesTest extends TestCase
{
    public function test_refresh_cookie_is_httponly_secure_strict_and_path_scoped(): void
    {
        $cookie = SessionCookies::setRefresh('raw-token', strtotime('2026-07-01 00:00:00 UTC'));

        self::assertStringStartsWith('ni_refresh=raw-token;', $cookie);
        self::assertStringContainsString('Path=/auth', $cookie);
        self::assertStringContainsString('HttpOnly', $cookie);
        self::assertStringContainsString('Secure', $cookie);
        self::assertStringContainsString('SameSite=Strict', $cookie);
    }

    public function test_csrf_cookie_is_readable_but_secure_and_strict(): void
    {
        $cookie = SessionCookies::setCsrf('csrf-token', strtotime('2026-07-01 00:00:00 UTC'));

        self::assertStringStartsWith('ni_csrf=csrf-token;', $cookie);
        self::assertStringContainsString('Path=/', $cookie);
        // Double-submit token must be JS-readable: it is deliberately NOT HttpOnly.
        self::assertStringNotContainsString('HttpOnly', $cookie);
        self::assertStringContainsString('Secure', $cookie);
        self::assertStringContainsString('SameSite=Strict', $cookie);
    }

    public function test_clear_cookies_expire_in_the_past(): void
    {
        self::assertStringContainsString('Max-Age=0', SessionCookies::clearRefresh());
        self::assertStringContainsString('Expires=Thu, 01 Jan 1970', SessionCookies::clearRefresh());
        self::assertStringContainsString('Max-Age=0', SessionCookies::clearCsrf());
    }

    public function test_reads_tokens_from_cookie_params(): void
    {
        $request = (new Psr17Factory())->createServerRequest('POST', '/auth/refresh')
            ->withCookieParams([
                SessionCookies::REFRESH_COOKIE => 'r-value',
                SessionCookies::CSRF_COOKIE => 'c-value',
            ]);

        self::assertSame('r-value', SessionCookies::refreshToken($request));
        self::assertSame('c-value', SessionCookies::csrfCookieValue($request));
    }

    public function test_absent_cookies_read_as_null(): void
    {
        $request = (new Psr17Factory())->createServerRequest('POST', '/auth/refresh');

        self::assertNull(SessionCookies::refreshToken($request));
        self::assertNull(SessionCookies::csrfCookieValue($request));
    }
}
