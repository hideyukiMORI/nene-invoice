<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\ServiceApi;

use Nene2\Error\ProblemDetailsResponseFactory;
use NeneInvoice\ServiceApi\ServiceScopeMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class ServiceScopeMiddlewareTest extends TestCase
{
    private const CLAIMS = 'nene2.auth.claims';

    private Psr17Factory $psr17;
    private ServiceScopeMiddleware $middleware;

    protected function setUp(): void
    {
        $this->psr17 = new Psr17Factory();
        $this->middleware = new ServiceScopeMiddleware(
            new ProblemDetailsResponseFactory($this->psr17, $this->psr17, 'https://nene-invoice.dev/problems/'),
        );
    }

    private function handler(): RequestHandlerInterface
    {
        $psr17 = $this->psr17;

        return new class ($psr17) implements RequestHandlerInterface {
            public function __construct(private readonly Psr17Factory $psr17)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->psr17->createResponse(200);
            }
        };
    }

    private function request(string $path, mixed $claims): ServerRequestInterface
    {
        $request = $this->psr17->createServerRequest('GET', $path);

        return $claims === null ? $request : $request->withAttribute(self::CLAIMS, $claims);
    }

    public function test_service_token_with_scope_passes(): void
    {
        $response = $this->middleware->process(
            $this->request('/api/invoices', ['org' => 1, 'scopes' => ['read:invoices']]),
            $this->handler(),
        );

        self::assertSame(200, $response->getStatusCode());
    }

    public function test_operator_token_without_scopes_is_rejected(): void
    {
        $response = $this->middleware->process(
            $this->request('/api/invoices', ['sub' => 7, 'role' => 'admin', 'org' => 1]),
            $this->handler(),
        );

        self::assertSame(403, $response->getStatusCode());
    }

    public function test_service_token_missing_required_scope_is_rejected(): void
    {
        $response = $this->middleware->process(
            $this->request('/api/invoices', ['org' => 1, 'scopes' => ['write:payments']]),
            $this->handler(),
        );

        self::assertSame(403, $response->getStatusCode());
    }

    public function test_service_token_without_organization_is_rejected(): void
    {
        $response = $this->middleware->process(
            $this->request('/api/invoices', ['scopes' => ['read:invoices']]),
            $this->handler(),
        );

        self::assertSame(403, $response->getStatusCode());
    }

    public function test_non_api_paths_pass_through(): void
    {
        $response = $this->middleware->process(
            $this->request('/admin/invoices', ['sub' => 7, 'role' => 'admin', 'org' => 1]),
            $this->handler(),
        );

        self::assertSame(200, $response->getStatusCode());
    }
}
