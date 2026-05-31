<?php

declare(strict_types=1);

namespace NeneInvoice\Payment;

use Nene2\Routing\Router;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Registers payment routes, nested under an invoice. Reads require `view_billing`,
 * recording a payment requires `manage_billing` (both resolved by the
 * `/admin/invoices` prefix in CapabilityResolver); all scoped to the caller's org.
 */
final readonly class PaymentRouteRegistrar
{
    public function __construct(
        private RecordPaymentHandler $recordHandler,
        private ListPaymentsHandler $listHandler,
        private ExportPaymentsCsvHandler $exportCsvHandler,
    ) {
    }

    public function __invoke(Router $router): void
    {
        $record    = $this->recordHandler;
        $list      = $this->listHandler;
        $exportCsv = $this->exportCsvHandler;

        $router->get('/admin/invoices/{id}/payments', static fn (ServerRequestInterface $r) => $list->handle($r));
        $router->post('/admin/invoices/{id}/payments', static fn (ServerRequestInterface $r) => $record->handle($r));
        $router->get('/admin/payments/export', static fn (ServerRequestInterface $r) => $exportCsv->handle($r));
    }
}
