<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\ServiceApi;

use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\ServiceApi\ServiceScopeMiddleware;
use NeneInvoice\ServiceToken\ServiceTokenAuthorizerInterface;
use NeneInvoice\Tests\Support\RecordingRequestHandler;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

final class ServiceScopeMiddlewareTest extends TestCase
{
    private const CLAIMS = 'nene2.auth.claims';

    private Psr17Factory $psr17;
    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $holder;
    /** @var list<string> jtis the fake authorizer treats as revoked */
    private array $revoked = [];
    private ServiceScopeMiddleware $middleware;

    protected function setUp(): void
    {
        $this->psr17 = new Psr17Factory();
        $this->holder = new RequestScopedHolder();

        $revoked = &$this->revoked;
        $authorizer = new class ($revoked) implements ServiceTokenAuthorizerInterface {
            /** @param list<string> $revoked */
            public function __construct(private array &$revoked)
            {
            }

            public function isActive(string $jti): bool
            {
                return !in_array($jti, $this->revoked, true);
            }
        };

        $this->middleware = new ServiceScopeMiddleware(
            new ProblemDetailsResponseFactory($this->psr17, $this->psr17, 'https://nene-invoice.dev/problems/'),
            $this->holder,
            $authorizer,
        );
    }

    private function handler(): RecordingRequestHandler
    {
        return new RecordingRequestHandler($this->psr17);
    }

    private function request(string $path, mixed $claims): ServerRequestInterface
    {
        $request = $this->psr17->createServerRequest('GET', $path);

        return $claims === null ? $request : $request->withAttribute(self::CLAIMS, $claims);
    }

    public function test_service_token_with_scope_passes(): void
    {
        $response = $this->middleware->process(
            $this->request('/api/invoices', ['org' => 9, 'scopes' => ['read:invoices']]),
            $this->handler(),
        );

        self::assertSame(200, $response->getStatusCode());
        // The service token's org is published to the holder for repositories.
        self::assertSame(9, $this->holder->get());
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

    public function test_active_registered_token_passes(): void
    {
        $response = $this->middleware->process(
            $this->request('/api/invoices', ['org' => 4, 'scopes' => ['read:invoices'], 'jti' => 'live-jti']),
            $this->handler(),
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(4, $this->holder->get());
    }

    public function test_revoked_token_is_rejected_with_401(): void
    {
        $this->revoked = ['dead-jti'];

        $response = $this->middleware->process(
            $this->request('/api/invoices', ['org' => 4, 'scopes' => ['read:invoices'], 'jti' => 'dead-jti']),
            $this->handler(),
        );

        self::assertSame(401, $response->getStatusCode());
    }

    public function test_legacy_token_without_jti_skips_revocation_check(): void
    {
        // Even with a non-empty revocation set, a token carrying no jti predates
        // the registry and is accepted on signature + scope alone.
        $this->revoked = ['anything'];

        $response = $this->middleware->process(
            $this->request('/api/invoices', ['org' => 4, 'scopes' => ['read:invoices']]),
            $this->handler(),
        );

        self::assertSame(200, $response->getStatusCode());
    }
}
