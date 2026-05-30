<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Organization\Resolution;

use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Organization\Organization;
use NeneInvoice\Organization\Resolution\EnvResolutionStrategy;
use NeneInvoice\Organization\Resolution\OrgResolverMiddleware;
use NeneInvoice\Tests\Support\InMemoryOrganizationRepository;
use NeneInvoice\Tests\Support\RecordingRequestHandler;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

final class OrgResolverMiddlewareTest extends TestCase
{
    private Psr17Factory $psr17;
    private InMemoryOrganizationRepository $orgs;
    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $holder;
    private ProblemDetailsResponseFactory $problemDetails;

    protected function setUp(): void
    {
        $this->psr17          = new Psr17Factory();
        $this->orgs           = new InMemoryOrganizationRepository();
        $this->holder         = new RequestScopedHolder();
        $this->problemDetails = new ProblemDetailsResponseFactory($this->psr17, $this->psr17, 'https://nene-invoice.dev/problems/');
    }

    private function passthroughHandler(): RecordingRequestHandler
    {
        return new RecordingRequestHandler($this->psr17);
    }

    private function middleware(string $slug, bool $fallback): OrgResolverMiddleware
    {
        return new OrgResolverMiddleware(
            $this->holder,
            $this->orgs,
            $this->problemDetails,
            new EnvResolutionStrategy($slug),
            $fallback,
        );
    }

    public function test_resolves_org_by_slug_and_sets_holder(): void
    {
        $id = $this->orgs->save(new Organization(name: 'Acme', slug: 'acme', plan: 'free', isActive: true));

        $handler = $this->passthroughHandler();
        $request = $this->psr17->createServerRequest('GET', 'https://app.example.com/admin/invoices');

        $response = $this->middleware('acme', false)->process($request, $handler);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame($id, $this->holder->get());
        self::assertSame($id, $handler->seen?->getAttribute('nene2.org.id'));
    }

    public function test_bypass_paths_skip_resolution(): void
    {
        $handler = $this->passthroughHandler();

        foreach (['/health', '/auth/login', '/api/invoices', '/invoices/download/x', '/admin/organizations'] as $path) {
            $request  = $this->psr17->createServerRequest('GET', "https://app.example.com{$path}");
            $response = $this->middleware('', false)->process($request, $handler);
            self::assertSame(200, $response->getStatusCode(), "bypass failed for {$path}");
        }
    }

    public function test_unresolved_returns_404_org_not_resolved(): void
    {
        $request  = $this->psr17->createServerRequest('GET', 'https://app.example.com/admin/invoices');
        $response = $this->middleware('', false)->process($request, $this->passthroughHandler());

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('organization-not-resolved', (string) $response->getBody());
    }

    public function test_unknown_slug_returns_404_org_not_found(): void
    {
        $request  = $this->psr17->createServerRequest('GET', 'https://app.example.com/admin/invoices');
        $response = $this->middleware('ghost', false)->process($request, $this->passthroughHandler());

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('organization-not-found', (string) $response->getBody());
    }

    public function test_inactive_org_returns_403(): void
    {
        $this->orgs->save(new Organization(name: 'Acme', slug: 'acme', plan: 'free', isActive: false));

        $request  = $this->psr17->createServerRequest('GET', 'https://app.example.com/admin/invoices');
        $response = $this->middleware('acme', false)->process($request, $this->passthroughHandler());

        self::assertSame(403, $response->getStatusCode());
        self::assertStringContainsString('organization-inactive', (string) $response->getBody());
    }

    public function test_sole_org_fallback_uses_only_organization(): void
    {
        $id = $this->orgs->save(new Organization(name: 'Only', slug: 'only', plan: 'free', isActive: true));

        $handler  = $this->passthroughHandler();
        $request  = $this->psr17->createServerRequest('GET', 'https://app.example.com/admin/invoices');
        $response = $this->middleware('', true)->process($request, $handler);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame($id, $this->holder->get());
    }

    public function test_sole_org_fallback_inert_when_multiple_orgs(): void
    {
        $this->orgs->save(new Organization(name: 'A', slug: 'a', plan: 'free', isActive: true));
        $this->orgs->save(new Organization(name: 'B', slug: 'b', plan: 'free', isActive: true));

        $request  = $this->psr17->createServerRequest('GET', 'https://app.example.com/admin/invoices');
        $response = $this->middleware('', true)->process($request, $this->passthroughHandler());

        self::assertSame(404, $response->getStatusCode());
    }
}
