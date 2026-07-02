<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Http;

use NeneInvoice\Http\SpaShell;
use Nyholm\Psr7\Factory\Psr17Factory;
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

    private function shell(): SpaShell
    {
        return new SpaShell($this->shellPath, $this->psr17, $this->psr17);
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
}
