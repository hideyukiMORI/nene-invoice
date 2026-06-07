<?php

declare(strict_types=1);

namespace NeneInvoice\Quote;

use NeneInvoice\Support\Jst;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `GET /admin/quotes/export` — exports quotes as a CSV file, applying the same
 * filters as the list endpoint (the export mirrors what the list shows).
 * Requires ViewBilling capability (resolved by the `/admin/quotes` prefix).
 */
final readonly class ExportQuotesCsvHandler implements RequestHandlerInterface
{
    public function __construct(
        private ExportQuotesCsvUseCaseInterface $useCase,
        private Psr17Factory $psr17,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $filter   = QuoteListFilterFactory::fromQueryParams($request->getQueryParams());
        $csv      = $this->useCase->execute($filter);
        $filename = 'quotes-' . Jst::today() . '.csv';
        $stream   = $this->psr17->createStream($csv);

        return $this->psr17->createResponse(200)
            ->withHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->withHeader('Content-Length', (string) strlen($csv))
            ->withBody($stream);
    }
}
