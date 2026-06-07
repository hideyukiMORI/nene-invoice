<?php

declare(strict_types=1);

namespace NeneInvoice\Item;

use Nene2\Routing\Router;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Registers item-master (品目) routes. Reads require `view_billing`, mutations
 * require `manage_billing` (CapabilityMiddleware); all scoped to the caller's
 * organization.
 */
final readonly class ItemRouteRegistrar
{
    public function __construct(
        private ListItemsHandler $listHandler,
        private GetItemByIdHandler $getHandler,
        private CreateItemHandler $createHandler,
        private UpdateItemHandler $updateHandler,
        private DeleteItemHandler $deleteHandler,
        private ExportItemsCsvHandler $exportCsvHandler,
        private GetItemsImportTemplateHandler $importTemplateHandler,
        private ImportItemsCsvHandler $importCsvHandler,
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
        $importTemplate = $this->importTemplateHandler;
        $importCsv = $this->importCsvHandler;

        $router->get('/admin/items', static fn (ServerRequestInterface $r) => $list->handle($r));
        $router->post('/admin/items', static fn (ServerRequestInterface $r) => $create->handle($r));
        // Static segments take priority over {id} per Router spec.
        $router->get('/admin/items/export', static fn (ServerRequestInterface $r) => $exportCsv->handle($r));
        $router->get('/admin/items/import-template', static fn (ServerRequestInterface $r) => $importTemplate->handle($r));
        $router->post('/admin/items/import', static fn (ServerRequestInterface $r) => $importCsv->handle($r));
        $router->get('/admin/items/{id}', static fn (ServerRequestInterface $r) => $get->handle($r));
        $router->patch('/admin/items/{id}', static fn (ServerRequestInterface $r) => $update->handle($r));
        $router->delete('/admin/items/{id}', static fn (ServerRequestInterface $r) => $delete->handle($r));
    }
}
