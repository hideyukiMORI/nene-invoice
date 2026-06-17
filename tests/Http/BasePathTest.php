<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Http;

use NeneInvoice\Http\BasePath;
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
    }

    #[DataProvider('apiPaths')]
    public function test_classifies_api_vs_spa(string $path, bool $isApi): void
    {
        self::assertSame($isApi, BasePath::isApiPath($path));
    }
}
