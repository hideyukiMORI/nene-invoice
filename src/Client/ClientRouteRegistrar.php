<?php

declare(strict_types=1);

namespace NeneInvoice\Client;

use Nene2\Routing\Router;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Registers client (取引先) routes. Reads require `view_billing`, mutations
 * require `manage_billing` (CapabilityMiddleware); all scoped to the caller's
 * organization. Write routes are added in a later PR.
 */
final readonly class ClientRouteRegistrar
{
    public function __construct(
        private ListClientsHandler $listHandler,
        private GetClientByIdHandler $getHandler,
    ) {
    }

    public function __invoke(Router $router): void
    {
        $list = $this->listHandler;
        $get = $this->getHandler;

        $router->get('/admin/clients', static fn (ServerRequestInterface $r) => $list->handle($r));
        $router->get('/admin/clients/{id}', static fn (ServerRequestInterface $r) => $get->handle($r));
    }
}
