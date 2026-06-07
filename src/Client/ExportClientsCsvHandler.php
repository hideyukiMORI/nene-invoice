<?php

declare(strict_types=1);

namespace NeneInvoice\Client;

use NeneInvoice\Support\Jst;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `GET /admin/clients/export` — exports clients as a CSV file, applying the same
 * filter as the list endpoint (the export mirrors what the list shows).
 * Requires ViewBilling capability (resolved by the `/admin/clients` prefix).
 */
final readonly class ExportClientsCsvHandler implements RequestHandlerInterface
{
    public function __construct(
        private ExportClientsCsvUseCaseInterface $useCase,
        private Psr17Factory $psr17,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $filter   = ClientListFilterFactory::fromQueryParams($request->getQueryParams());
        $csv      = $this->useCase->execute($filter);
        $filename = 'clients-' . Jst::today() . '.csv';
        $stream   = $this->psr17->createStream($csv);

        return $this->psr17->createResponse(200)
            ->withHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->withHeader('Content-Length', (string) strlen($csv))
            ->withBody($stream);
    }
}
