<?php

declare(strict_types=1);

namespace NeneInvoice\Item;

use NeneInvoice\Support\Jst;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `GET /admin/items/export` — exports items as a CSV file (import-template shape),
 * applying the same filter as the list. Requires ViewBilling capability.
 */
final readonly class ExportItemsCsvHandler implements RequestHandlerInterface
{
    public function __construct(
        private ExportItemsCsvUseCaseInterface $useCase,
        private Psr17Factory $psr17,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $filter   = ItemListFilterFactory::fromQueryParams($request->getQueryParams());
        $csv      = $this->useCase->execute($filter);
        $filename = 'items-' . Jst::today() . '.csv';
        $stream   = $this->psr17->createStream($csv);

        return $this->psr17->createResponse(200)
            ->withHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->withHeader('Content-Length', (string) strlen($csv))
            ->withBody($stream);
    }
}
