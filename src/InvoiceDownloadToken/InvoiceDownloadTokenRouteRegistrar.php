<?php

declare(strict_types=1);

namespace NeneInvoice\InvoiceDownloadToken;

use Nene2\Routing\Router;
use Psr\Http\Message\ServerRequestInterface;

final readonly class InvoiceDownloadTokenRouteRegistrar
{
    public function __construct(
        private GenerateDownloadTokenHandler $generateHandler,
        private DownloadInvoicePdfHandler $downloadHandler,
    ) {
    }

    public function __invoke(Router $router): void
    {
        $generate = $this->generateHandler;
        $download = $this->downloadHandler;

        $router->post('/admin/invoices/{id}/download-token', static fn (ServerRequestInterface $r) => $generate->handle($r));
        $router->get('/invoices/download/{token}', static fn (ServerRequestInterface $r) => $download->handle($r));
    }
}
