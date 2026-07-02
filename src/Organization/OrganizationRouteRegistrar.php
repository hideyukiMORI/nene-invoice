<?php

declare(strict_types=1);

namespace NeneInvoice\Organization;

use Nene2\Routing\Router;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Registers organization (tenant) management routes. All require the
 * `manage_organizations` capability (superadmin), enforced by CapabilityMiddleware.
 */
final readonly class OrganizationRouteRegistrar
{
    public function __construct(
        private ListOrganizationsHandler $listHandler,
        private GetOrganizationByIdHandler $getHandler,
        private CreateOrganizationHandler $createHandler,
        private UpdateOrganizationHandler $updateHandler,
        private DeleteOrganizationHandler $deleteHandler,
    ) {
    }

    public function __invoke(Router $router): void
    {
        $list = $this->listHandler;
        $get = $this->getHandler;
        $create = $this->createHandler;
        $update = $this->updateHandler;
        $delete = $this->deleteHandler;

        $router->get('/admin/organizations', static fn (ServerRequestInterface $r) => $list->handle($r));
        $router->post('/admin/organizations', static fn (ServerRequestInterface $r) => $create->handle($r));
        $router->get('/admin/organizations/{id}', static fn (ServerRequestInterface $r) => $get->handle($r));
        $router->patch('/admin/organizations/{id}', static fn (ServerRequestInterface $r) => $update->handle($r));
        $router->delete('/admin/organizations/{id}', static fn (ServerRequestInterface $r) => $delete->handle($r));
    }
}
