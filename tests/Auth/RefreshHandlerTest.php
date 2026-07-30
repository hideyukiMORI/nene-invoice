<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Auth;

use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
use NeneInvoice\Auth\CsrfGuard;
use NeneInvoice\Auth\InvalidRefreshTokenException;
use NeneInvoice\Auth\IssuedRefreshToken;
use NeneInvoice\Auth\RefreshedSession;
use NeneInvoice\Auth\RefreshHandler;
use NeneInvoice\Auth\RefreshSessionUseCaseInterface;
use NeneInvoice\Auth\SessionCookies;
use NeneInvoice\Http\BasePath;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The rotation half of the #38 root fix.
 *
 * `OrgResolverMiddlewareTest` proves the middleware records the slug-scoped app
 * base; `SessionCookiesTest` proves the builder turns a base into a `Path`. This
 * covers the join: the handler must reissue the rotated cookies at the **app**
 * base (install base + slug), not the slug-stripped install base. Getting this
 * wrong lands `ni_refresh` at `/{base}/auth`, so the next `/{slug}/auth/refresh`
 * re-presents a spent token and the server-side reuse defense burns the family
 * (hard logout). Silent refresh in path mode depends on this.
 */
final class RefreshHandlerTest extends TestCase
{
    private Psr17Factory $psr17;

    protected function setUp(): void
    {
        $this->psr17 = new Psr17Factory();
    }

    private function handler(): RefreshHandler
    {
        $useCase = new class () implements RefreshSessionUseCaseInterface {
            public function execute(string $rawToken): RefreshedSession
            {
                return new RefreshedSession(
                    accessToken: 'access-token',
                    refreshToken: new IssuedRefreshToken('rotated-raw-token', '2026-08-30T00:00:00+00:00', 1_787_000_000),
                );
            }
        };

        return new RefreshHandler(
            $useCase,
            new JsonResponseFactory($this->psr17, $this->psr17),
            new ProblemDetailsResponseFactory($this->psr17, $this->psr17, 'https://nene-invoice.dev/problems/'),
        );
    }

    /** A request that passes the cookie + double-submit CSRF checks. */
    private function signedInRequest(string $path): ServerRequestInterface
    {
        return $this->psr17
            ->createServerRequest('POST', 'https://app.example.com' . $path)
            ->withCookieParams([
                SessionCookies::REFRESH_COOKIE => 'current-raw-token',
                SessionCookies::CSRF_COOKIE => 'csrf-value',
            ])
            ->withHeader(CsrfGuard::HEADER, 'csrf-value');
    }

    /** @return array<int, string> */
    private function setCookies(ServerRequestInterface $request): array
    {
        return $this->handler()->handle($request)->getHeader('Set-Cookie');
    }

    private static function cookieNamed(string $name, string ...$headers): string
    {
        foreach ($headers as $header) {
            if (str_starts_with($header, $name . '=')) {
                return $header;
            }
        }

        self::fail("No Set-Cookie for {$name}");
    }

    public function test_rotated_cookies_are_scoped_to_the_slug_in_path_tenancy(): void
    {
        $request = $this->signedInRequest('/acme/auth/refresh')
            ->withAttribute(BasePath::APP_BASE_ATTRIBUTE, '/acme');

        $cookies = $this->setCookies($request);

        self::assertStringContainsString(
            'Path=/acme/auth',
            self::cookieNamed(SessionCookies::REFRESH_COOKIE, ...$cookies),
        );
        self::assertStringContainsString(
            'Path=/acme/',
            self::cookieNamed(SessionCookies::CSRF_COOKIE, ...$cookies),
        );
    }

    public function test_rotated_cookies_keep_the_install_base_under_a_subdirectory_install(): void
    {
        $request = $this->signedInRequest('/invoice/acme/auth/refresh')
            ->withAttribute(BasePath::APP_BASE_ATTRIBUTE, '/invoice/acme');

        $cookies = $this->setCookies($request);

        self::assertStringContainsString(
            'Path=/invoice/acme/auth',
            self::cookieNamed(SessionCookies::REFRESH_COOKIE, ...$cookies),
        );
    }

    public function test_single_tenant_mode_scopes_to_the_install_base(): void
    {
        // No app-base attribute: `appBaseFromRequest` falls back to the install
        // base, which is `''` at the document root.
        $cookies = $this->setCookies($this->signedInRequest('/auth/refresh'));

        self::assertStringContainsString(
            'Path=/auth',
            self::cookieNamed(SessionCookies::REFRESH_COOKIE, ...$cookies),
        );
    }

    public function test_fail_closed_clears_cookies_at_the_same_scope(): void
    {
        $failing = new class () implements RefreshSessionUseCaseInterface {
            public function execute(string $rawToken): RefreshedSession
            {
                throw new InvalidRefreshTokenException();
            }
        };
        $handler = new RefreshHandler(
            $failing,
            new JsonResponseFactory($this->psr17, $this->psr17),
            new ProblemDetailsResponseFactory($this->psr17, $this->psr17, 'https://nene-invoice.dev/problems/'),
        );

        $request = $this->signedInRequest('/acme/auth/refresh')
            ->withAttribute(BasePath::APP_BASE_ATTRIBUTE, '/acme');
        $response = $handler->handle($request);

        self::assertSame(401, $response->getStatusCode());
        // A clear that misses the scope leaves the stale cookie in place.
        self::assertStringContainsString(
            'Path=/acme/auth',
            self::cookieNamed(SessionCookies::REFRESH_COOKIE, ...$response->getHeader('Set-Cookie')),
        );
    }
}
