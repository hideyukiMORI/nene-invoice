<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Http;

use NeneInvoice\Http\BasePath;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BasePathTest extends TestCase
{
    /** @return iterable<string, array{0: string, 1: string}> */
    public static function scriptNames(): iterable
    {
        yield 'document root' => ['/index.php', ''];
        yield 'subdirectory' => ['/invoice/index.php', '/invoice'];
        yield 'nested suite' => ['/NeNeSuite/invoice/index.php', '/NeNeSuite/invoice'];
    }

    #[DataProvider('scriptNames')]
    public function test_detects_base_from_script_name(string $scriptName, string $expected): void
    {
        self::assertSame($expected, BasePath::detect(['SCRIPT_NAME' => $scriptName]));
    }

    public function test_override_wins_and_is_normalized(): void
    {
        self::assertSame('/invoice', BasePath::detect(['SCRIPT_NAME' => '/index.php'], '/invoice/'));
        self::assertSame('', BasePath::detect(['SCRIPT_NAME' => '/sub/index.php'], '/'));
    }

    public function test_missing_script_name_is_root(): void
    {
        self::assertSame('', BasePath::detect([]));
    }

    public function test_untrusted_script_name_falls_back_to_root(): void
    {
        // php built-in server (router mode) sets SCRIPT_NAME to the request path,
        // not the front controller — must not be mistaken for the base.
        self::assertSame('', BasePath::detect(['SCRIPT_NAME' => '/admin/me']));
        self::assertSame('', BasePath::detect(['SCRIPT_NAME' => '/dashboard']));
    }

    /** @return iterable<string, array{0: string, 1: string, 2: string}> */
    public static function stripCases(): iterable
    {
        yield 'root passthrough' => ['/health', '', '/health'];
        yield 'subdir api' => ['/invoice/auth/login', '/invoice', '/auth/login'];
        yield 'subdir bare base' => ['/invoice', '/invoice', '/'];
        yield 'nested' => ['/NeNeSuite/invoice/admin/me', '/NeNeSuite/invoice', '/admin/me'];
        yield 'not under base' => ['/other/x', '/invoice', '/other/x'];
    }

    #[DataProvider('stripCases')]
    public function test_strips_base_prefix(string $path, string $base, string $expected): void
    {
        self::assertSame($expected, BasePath::strip($path, $base));
    }

    /** @return iterable<string, array{0: string, 1: bool}> */
    public static function apiPaths(): iterable
    {
        yield 'auth' => ['/auth/login', true];
        yield 'admin' => ['/admin/me', true];
        yield 'api' => ['/api/invoices', true];
        yield 'health' => ['/health', true];
        yield 'root is spa' => ['/', false];
        yield 'dashboard is spa' => ['/dashboard', false];
        yield 'admin-ish but not boundary' => ['/administration', false];
        // Public share/payment links must reach the router, never the SPA shell (#620).
        yield 'public pdf download' => ['/invoices/download/tok_abc123', true];
        yield 'public pay page' => ['/pay/tok_abc123', true];
        // ...but the SPA's own /invoices screens keep getting the shell (#620).
        yield 'invoices list is spa' => ['/invoices', false];
        yield 'invoice create is spa' => ['/invoices/new', false];
        yield 'invoice detail is spa' => ['/invoices/818', false];
        yield 'invoices-ish but not boundary' => ['/invoicesdownload', false];
        yield 'pay-ish but not boundary' => ['/payments', false];
    }

    #[DataProvider('apiPaths')]
    public function test_classifies_api_vs_spa(string $path, bool $isApi): void
    {
        self::assertSame($isApi, BasePath::isApiPath($path));
    }

    public function test_app_base_falls_back_to_install_base_when_no_slug_axis(): void
    {
        // No app-base attribute (single/subdomain mode) → the install base (#38).
        $request = (new Psr17Factory())->createServerRequest('GET', 'https://app.example.com/auth/refresh')
            ->withAttribute(BasePath::REQUEST_ATTRIBUTE, '/invoice');

        self::assertSame('/invoice', BasePath::appBaseFromRequest($request));
    }

    public function test_app_base_uses_the_slug_scoped_attribute_when_present(): void
    {
        // Path tenancy: OrgResolverMiddleware set the slug-scoped app base (#38).
        $request = (new Psr17Factory())->createServerRequest('GET', 'https://app.example.com/auth/refresh')
            ->withAttribute(BasePath::REQUEST_ATTRIBUTE, '/invoice')
            ->withAttribute(BasePath::APP_BASE_ATTRIBUTE, '/invoice/acme');

        self::assertSame('/invoice/acme', BasePath::appBaseFromRequest($request));
    }
}
