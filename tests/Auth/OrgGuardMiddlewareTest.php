<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Auth;

use Nene2\Error\ProblemDetailsResponseFactory;
use NeneInvoice\Auth\OrgGuardMiddleware;
use NeneInvoice\Tests\Support\RecordingRequestHandler;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

final class OrgGuardMiddlewareTest extends TestCase
{
    private Psr17Factory $psr17;
    private OrgGuardMiddleware $middleware;

    protected function setUp(): void
    {
        $this->psr17      = new Psr17Factory();
        $this->middleware = new OrgGuardMiddleware(
            new ProblemDetailsResponseFactory($this->psr17, $this->psr17, 'https://nene-invoice.dev/problems/'),
        );
    }

    private function okHandler(): RecordingRequestHandler
    {
        return new RecordingRequestHandler($this->psr17);
    }

    public function test_passes_when_token_org_matches_resolved_org(): void
    {
        $request = $this->psr17->createServerRequest('GET', 'https://app.example.com/admin/invoices')
            ->withAttribute('nene2.org.id', 7)
            ->withAttribute('nene2.auth.claims', ['sub' => 1, 'role' => 'admin', 'org' => 7]);

        self::assertSame(200, $this->middleware->process($request, $this->okHandler())->getStatusCode());
    }

    public function test_rejects_when_token_org_differs(): void
    {
        $request = $this->psr17->createServerRequest('GET', 'https://app.example.com/admin/invoices')
            ->withAttribute('nene2.org.id', 7)
            ->withAttribute('nene2.auth.claims', ['sub' => 1, 'role' => 'admin', 'org' => 8]);

        $response = $this->middleware->process($request, $this->okHandler());
        self::assertSame(403, $response->getStatusCode());
        self::assertStringContainsString('organization-mismatch', (string) $response->getBody());
    }

    public function test_superadmin_with_null_org_passes(): void
    {
        $request = $this->psr17->createServerRequest('GET', 'https://app.example.com/admin/invoices')
            ->withAttribute('nene2.org.id', 7)
            ->withAttribute('nene2.auth.claims', ['sub' => 1, 'role' => 'superadmin', 'org' => null]);

        self::assertSame(200, $this->middleware->process($request, $this->okHandler())->getStatusCode());
    }

    public function test_passes_when_no_resolved_org(): void
    {
        // Bypass route: no nene2.org.id attribute.
        $request = $this->psr17->createServerRequest('GET', 'https://app.example.com/api/invoices')
            ->withAttribute('nene2.auth.claims', ['sub' => 1, 'role' => 'admin', 'org' => 8]);

        self::assertSame(200, $this->middleware->process($request, $this->okHandler())->getStatusCode());
    }

    public function test_passes_when_no_claims(): void
    {
        // Public route: resolved org but no verified claims.
        $request = $this->psr17->createServerRequest('GET', 'https://app.example.com/admin/invoices')
            ->withAttribute('nene2.org.id', 7);

        self::assertSame(200, $this->middleware->process($request, $this->okHandler())->getStatusCode());
    }
}
