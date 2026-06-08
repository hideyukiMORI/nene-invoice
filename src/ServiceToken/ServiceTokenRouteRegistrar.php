<?php

declare(strict_types=1);

namespace NeneInvoice\ServiceToken;

use Nene2\Routing\Router;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Registers service-token management routes (`/admin/service-tokens`). All
 * require ManageUsers (admin oversight) via CapabilityMiddleware, scoped to the
 * resolved organization (ADR 0006).
 */
final readonly class ServiceTokenRouteRegistrar
{
    public function __construct(
        private ListServiceTokensHandler $listHandler,
        private IssueServiceTokenHandler $issueHandler,
        private RevokeServiceTokenHandler $revokeHandler,
    ) {
    }

    public function __invoke(Router $router): void
    {
        $list = $this->listHandler;
        $issue = $this->issueHandler;
        $revoke = $this->revokeHandler;

        $router->get('/admin/service-tokens', static fn (ServerRequestInterface $r) => $list->handle($r));
        $router->post('/admin/service-tokens', static fn (ServerRequestInterface $r) => $issue->handle($r));
        $router->delete('/admin/service-tokens/{id}', static fn (ServerRequestInterface $r) => $revoke->handle($r));
    }
}
