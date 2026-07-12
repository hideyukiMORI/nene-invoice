<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Http;

use NeneInvoice\Http\SpaShell;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SpaShellTest extends TestCase
{
    private Psr17Factory $psr17;
    private string $shellPath;

    protected function setUp(): void
    {
        $this->psr17     = new Psr17Factory();
        $this->shellPath = tempnam(sys_get_temp_dir(), 'shell') ?: '';
        file_put_contents($this->shellPath, "<!doctype html>\n<html>\n<head>\n<title>NeNe</title>\n</head>\n<body></body>\n</html>");
    }

    protected function tearDown(): void
    {
        if ($this->shellPath !== '' && is_file($this->shellPath)) {
            unlink($this->shellPath);
        }
    }

    private function shell(?string $analyticsEndpoint = null): SpaShell
    {
        return new SpaShell($this->shellPath, $this->psr17, $this->psr17, $analyticsEndpoint);
    }

    public function test_root_install_injects_root_asset_and_app_base(): void
    {
        $response = $this->shell()->serve('', '');
        self::assertNotNull($response);

        $html = (string) $response->getBody();
        self::assertStringContainsString('<base href="/admin/" />', $html);
        self::assertStringContainsString('<meta name="app-base" content="/" />', $html);
        self::assertSame('text/html; charset=UTF-8', $response->getHeaderLine('Content-Type'));
    }

    public function test_path_tenant_diverges_asset_base_from_app_base(): void
    {
        // Installed under /invoice, tenant acme: assets stay install-relative,
        // the app (router + API) is scoped to the slug.
        $response = $this->shell()->serve('/invoice', '/invoice/acme');
        self::assertNotNull($response);

        $html = (string) $response->getBody();
        self::assertStringContainsString('<base href="/invoice/admin/" />', $html);
        self::assertStringContainsString('<meta name="app-base" content="/invoice/acme/" />', $html);
    }

    public function test_missing_shell_returns_null(): void
    {
        $shell = new SpaShell($this->shellPath . '.absent', $this->psr17, $this->psr17);

        self::assertNull($shell->serve('', ''));
    }

    public function test_analytics_disabled_by_default_injects_no_beacon_and_no_csp(): void
    {
        // The OSS default: env unset → the shell is the pre-analytics shell and
        // sets no CSP header (Apache's .htaccess default applies unchanged).
        $response = $this->shell()->serve('', '');
        self::assertNotNull($response);

        $html = (string) $response->getBody();
        self::assertStringNotContainsString('goatcounter', $html);
        self::assertStringNotContainsString('<script', $html);
        self::assertFalse($response->hasHeader('Content-Security-Policy'));
    }

    public function test_analytics_endpoint_injects_beacon_and_widens_csp(): void
    {
        $response = $this->shell('https://stats.example.test')->serve('', '');
        self::assertNotNull($response);

        $html = (string) $response->getBody();
        self::assertStringContainsString(
            '<script data-goatcounter="https://stats.example.test/count" async src="https://stats.example.test/count.js"></script>',
            $html,
        );
        // The base tags still come first (base precedes any relative URL).
        self::assertStringContainsString('<base href="/admin/" />', $html);

        $csp = $response->getHeaderLine('Content-Security-Policy');
        self::assertStringContainsString("script-src 'self' https://stats.example.test", $csp);
        self::assertStringContainsString("connect-src 'self' https://stats.example.test", $csp);
        self::assertStringContainsString("img-src 'self' data: https://stats.example.test", $csp);
        // The rest of the hardened default is intact.
        self::assertStringContainsString("frame-ancestors 'self'", $csp);
        self::assertStringContainsString("object-src 'none'", $csp);
    }

    public function test_trailing_slash_is_trimmed_from_endpoint(): void
    {
        $response = $this->shell('https://stats.example.test/')->serve('', '');
        self::assertNotNull($response);

        self::assertStringContainsString('src="https://stats.example.test/count.js"', (string) $response->getBody());
    }

    /**
     * A malformed or non-origin endpoint fails safe to disabled — no markup and
     * no CSP header — so a fat-fingered env can never inject a tag or a broken
     * header value.
     */
    #[DataProvider('invalidEndpoints')]
    public function test_invalid_endpoint_fails_safe_to_disabled(string $endpoint): void
    {
        $response = $this->shell($endpoint)->serve('', '');
        self::assertNotNull($response);

        self::assertStringNotContainsString('goatcounter', (string) $response->getBody());
        self::assertFalse($response->hasHeader('Content-Security-Policy'));
    }

    /** @return array<string, array{string}> */
    public static function invalidEndpoints(): array
    {
        return [
            'empty' => [''],
            'whitespace' => ['   '],
            'has path' => ['https://stats.example.test/count'],
            'has query' => ['https://stats.example.test?a=1'],
            'no scheme' => ['stats.example.test'],
            'crlf injection' => ["https://stats.example.test\r\nX-Evil: 1"],
            'javascript scheme' => ['javascript:alert(1)'],
        ];
    }
}
