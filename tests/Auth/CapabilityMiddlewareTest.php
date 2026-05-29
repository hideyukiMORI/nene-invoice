<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Auth;

use Nene2\Error\ProblemDetailsResponseFactory;
use NeneInvoice\Auth\CapabilityMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class CapabilityMiddlewareTest extends TestCase
{
    private Psr17Factory $psr17;
    private CapabilityMiddleware $middleware;

    protected function setUp(): void
    {
        $this->psr17 = new Psr17Factory();
        $this->middleware = new CapabilityMiddleware(
            new ProblemDetailsResponseFactory($this->psr17, $this->psr17, 'https://nene-invoice.dev/problems/'),
        );
    }

    public function test_blocks_role_lacking_capability_with_403(): void
    {
        $request = $this->request('GET', '/admin/organizations', ['role' => 'member']);

        $response = $this->middleware->process($request, $this->passThroughHandler());

        self::assertSame(403, $response->getStatusCode());
    }

    public function test_allows_role_with_capability(): void
    {
        $request = $this->request('GET', '/admin/organizations', ['role' => 'superadmin']);

        $response = $this->middleware->process($request, $this->passThroughHandler());

        self::assertSame(200, $response->getStatusCode());
    }

    public function test_passes_through_unauthenticated_requests(): void
    {
        $request = $this->psr17->createServerRequest('GET', '/health');

        $response = $this->middleware->process($request, $this->passThroughHandler());

        self::assertSame(200, $response->getStatusCode());
    }

    public function test_passes_through_routes_needing_no_capability(): void
    {
        $request = $this->request('GET', '/admin/me', ['role' => 'member']);

        $response = $this->middleware->process($request, $this->passThroughHandler());

        self::assertSame(200, $response->getStatusCode());
    }

    /** @param array<string, mixed> $claims */
    private function request(string $method, string $path, array $claims): ServerRequestInterface
    {
        return $this->psr17->createServerRequest($method, $path)->withAttribute('nene2.auth.claims', $claims);
    }

    private function passThroughHandler(): RequestHandlerInterface
    {
        return new class ($this->psr17) implements RequestHandlerInterface {
            public function __construct(private readonly Psr17Factory $psr17)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->psr17->createResponse(200);
            }
        };
    }
}
