<?php

declare(strict_types=1);

namespace NeneInvoice\Client;

use Nene2\Routing\Router;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Registers client (取引先) routes. Reads require `view_billing`, mutations
 * require `manage_billing` (CapabilityMiddleware); all scoped to the caller's
 * organization.
 */
final readonly class ClientRouteRegistrar
{
    public function __construct(
        private ListClientsHandler $listHandler,
        private GetClientByIdHandler $getHandler,
        private CreateClientHandler $createHandler,
        private UpdateClientHandler $updateHandler,
        private DeleteClientHandler $deleteHandler,
        private ExportClientsCsvHandler $exportCsvHandler,
    ) {
    }

    public function __invoke(Router $router): void
    {
        $list = $this->listHandler;
        $get = $this->getHandler;
        $create = $this->createHandler;
        $update = $this->updateHandler;
        $delete = $this->deleteHandler;
        $exportCsv = $this->exportCsvHandler;

        $router->get('/admin/clients', static fn (ServerRequestInterface $r) => $list->handle($r));
        $router->post('/admin/clients', static fn (ServerRequestInterface $r) => $create->handle($r));
        // Static segment 'export' takes priority over {id} per Router spec.
        $router->get('/admin/clients/export', static fn (ServerRequestInterface $r) => $exportCsv->handle($r));
        $router->get('/admin/clients/{id}', static fn (ServerRequestInterface $r) => $get->handle($r));
        $router->patch('/admin/clients/{id}', static fn (ServerRequestInterface $r) => $update->handle($r));
        $router->delete('/admin/clients/{id}', static fn (ServerRequestInterface $r) => $delete->handle($r));
    }
}
