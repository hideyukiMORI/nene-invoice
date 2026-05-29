<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

use Nene2\Routing\Router;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Registers invoice routes. Reads require `view_billing`, mutations require
 * `manage_billing`; all scoped to the caller's organization. The convert action
 * lives under `/admin/quotes/{id}/convert` (acts on a quote, produces an invoice).
 */
final readonly class InvoiceRouteRegistrar
{
    public function __construct(
        private ListInvoicesHandler $listHandler,
        private GetInvoiceByIdHandler $getHandler,
        private ConvertQuoteToInvoiceHandler $convertHandler,
    ) {
    }

    public function __invoke(Router $router): void
    {
        $list = $this->listHandler;
        $get = $this->getHandler;
        $convert = $this->convertHandler;

        $router->get('/admin/invoices', static fn (ServerRequestInterface $r) => $list->handle($r));
        $router->get('/admin/invoices/{id}', static fn (ServerRequestInterface $r) => $get->handle($r));
        $router->post('/admin/quotes/{id}/convert', static fn (ServerRequestInterface $r) => $convert->handle($r));
    }
}
