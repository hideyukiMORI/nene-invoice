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
        private CreateInvoiceHandler $createHandler,
        private ConvertQuoteToInvoiceHandler $convertHandler,
        private IssueInvoiceHandler $issueHandler,
        private GetInvoicePdfHandler $pdfHandler,
        private SendInvoiceEmailHandler $sendEmailHandler,
        private ExportInvoicesCsvHandler $exportCsvHandler,
    ) {
    }

    public function __invoke(Router $router): void
    {
        $list      = $this->listHandler;
        $get       = $this->getHandler;
        $create    = $this->createHandler;
        $convert   = $this->convertHandler;
        $issue     = $this->issueHandler;
        $pdf       = $this->pdfHandler;
        $sendEmail = $this->sendEmailHandler;
        $exportCsv = $this->exportCsvHandler;

        $router->get('/admin/invoices', static fn (ServerRequestInterface $r) => $list->handle($r));
        $router->post('/admin/invoices', static fn (ServerRequestInterface $r) => $create->handle($r));
        // Static segment 'export' takes priority over {id} per Router spec.
        $router->get('/admin/invoices/export', static fn (ServerRequestInterface $r) => $exportCsv->handle($r));
        $router->get('/admin/invoices/{id}', static fn (ServerRequestInterface $r) => $get->handle($r));
        $router->get('/admin/invoices/{id}/pdf', static fn (ServerRequestInterface $r) => $pdf->handle($r));
        $router->post('/admin/invoices/{id}/issue', static fn (ServerRequestInterface $r) => $issue->handle($r));
        $router->post('/admin/invoices/{id}/send-email', static fn (ServerRequestInterface $r) => $sendEmail->handle($r));
        $router->post('/admin/quotes/{id}/convert', static fn (ServerRequestInterface $r) => $convert->handle($r));
    }
}
