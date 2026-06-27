<?php

declare(strict_types=1);

namespace NeneInvoice\RecurringInvoice;

use Nene2\Routing\Router;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Registers recurring-invoice (継続請求) routes. Reads require `view_billing`,
 * mutations require `manage_billing` (CapabilityMiddleware); all scoped to the
 * caller's organization.
 */
final readonly class RecurringInvoiceRouteRegistrar
{
    public function __construct(
        private ListRecurringInvoicesHandler $listHandler,
        private GetRecurringInvoiceHandler $getHandler,
        private CreateRecurringInvoiceHandler $createHandler,
        private UpdateRecurringInvoiceHandler $updateHandler,
        private DeleteRecurringInvoiceHandler $deleteHandler,
    ) {
    }

    public function __invoke(Router $router): void
    {
        $list   = $this->listHandler;
        $get    = $this->getHandler;
        $create = $this->createHandler;
        $update = $this->updateHandler;
        $delete = $this->deleteHandler;

        $router->get('/admin/recurring-invoices', static fn (ServerRequestInterface $r) => $list->handle($r));
        $router->post('/admin/recurring-invoices', static fn (ServerRequestInterface $r) => $create->handle($r));
        $router->get('/admin/recurring-invoices/{id}', static fn (ServerRequestInterface $r) => $get->handle($r));
        $router->patch('/admin/recurring-invoices/{id}', static fn (ServerRequestInterface $r) => $update->handle($r));
        $router->delete('/admin/recurring-invoices/{id}', static fn (ServerRequestInterface $r) => $delete->handle($r));
    }
}
