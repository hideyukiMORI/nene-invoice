<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Http;

use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\RequestScopedHolder;
use Nene2\Routing\Router;
use NeneInvoice\Organization\Organization;
use NeneInvoice\Organization\Resolution\OrgResolverMiddleware;
use NeneInvoice\Organization\Resolution\PathPrefixResolutionStrategy;
use NeneInvoice\Tests\Support\InMemoryOrganizationRepository;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * End-to-end proof of 型B path tenancy: a `/<slug>/admin/...` request resolves
 * the org and reaches the `/admin/...` route (registered without the tenant
 * prefix), scoped to that org — exercising the real OrgResolverMiddleware, the
 * PathPrefixResolutionStrategy, and the real NENE2 Router together.
 */
final class PathTenancyRoutingTest extends TestCase
{
    private Psr17Factory $psr17;
    private InMemoryOrganizationRepository $orgs;
    private Router $router;
    private int $acmeId;
    private int $betaId;

    protected function setUp(): void
    {
        $this->psr17 = new Psr17Factory();
        $this->orgs  = new InMemoryOrganizationRepository();

        $this->acmeId = $this->orgs->save(new Organization(name: 'Acme', slug: 'acme', plan: 'free', isActive: true));
        $this->betaId = $this->orgs->save(new Organization(name: 'Beta', slug: 'beta', plan: 'free', isActive: true));

        // Route registered WITHOUT any tenant prefix, as the app registers it.
        $this->router = new Router();
        $this->router->get('/admin/dashboard', function (ServerRequestInterface $request): ResponseInterface {
            $response = $this->psr17->createResponse(200);
            $response->getBody()->write((string) json_encode([
                'org_id'   => $request->getAttribute('nene2.org.id'),
                'org_slug' => $request->getAttribute('nene2.org.slug'),
            ]));

            return $response;
        });
    }

    private function dispatch(string $path): ResponseInterface
    {
        $middleware = new OrgResolverMiddleware(
            new RequestScopedHolder(),
            $this->orgs,
            new ProblemDetailsResponseFactory($this->psr17, $this->psr17, 'https://nene-invoice.dev/problems/'),
            new PathPrefixResolutionStrategy(),
            false,
        );

        $request = $this->psr17->createServerRequest('GET', "https://host.example{$path}");

        return $middleware->process($request, $this->router);
    }

    public function test_tenant_prefix_reaches_admin_route_scoped_to_that_org(): void
    {
        $response = $this->dispatch('/acme/admin/dashboard');

        self::assertSame(200, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true);
        self::assertIsArray($payload);
        self::assertSame($this->acmeId, $payload['org_id']);
        self::assertSame('acme', $payload['org_slug']);
    }

    public function test_different_tenant_prefix_scopes_to_its_own_org(): void
    {
        $response = $this->dispatch('/beta/admin/dashboard');

        self::assertSame(200, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true);
        self::assertIsArray($payload);
        self::assertSame($this->betaId, $payload['org_id']);
        self::assertSame('beta', $payload['org_slug']);
    }

    public function test_missing_tenant_prefix_is_404(): void
    {
        // In path mode there is no sole-org fallback: `/admin/...` with no slug
        // treats `admin` as the (non-existent) slug and 404s before routing.
        $response = $this->dispatch('/admin/dashboard');

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('organization-not-found', (string) $response->getBody());
    }

    public function test_unknown_tenant_prefix_is_404(): void
    {
        $response = $this->dispatch('/ghost/admin/dashboard');

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('organization-not-found', (string) $response->getBody());
    }
}
