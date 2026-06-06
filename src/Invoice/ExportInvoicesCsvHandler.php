<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

use NeneInvoice\Support\Jst;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `GET /admin/invoices/export` — exports issued invoices as a CSV file.
 * Requires ViewBilling capability (resolved by the `/admin/invoices` prefix).
 */
final readonly class ExportInvoicesCsvHandler implements RequestHandlerInterface
{
    public function __construct(
        private ExportInvoicesCsvUseCaseInterface $useCase,
        private Psr17Factory $psr17,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $csv      = $this->useCase->execute();
        $filename = 'invoices-' . Jst::today() . '.csv';
        $stream   = $this->psr17->createStream($csv);

        return $this->psr17->createResponse(200)
            ->withHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->withHeader('Content-Length', (string) strlen($csv))
            ->withBody($stream);
    }
}
