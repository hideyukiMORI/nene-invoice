<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

use Nene2\Http\JsonResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `GET /admin/invoices` — lists invoice headers in the resolved organization
 * (scoped by the repository via the org holder).
 */
final readonly class ListInvoicesHandler implements RequestHandlerInterface
{
    private const DEFAULT_LIMIT = 20;
    private const MAX_LIMIT = 100;

    public function __construct(
        private ListInvoicesUseCase $useCase,
        private JsonResponseFactory $json,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $query = $request->getQueryParams();

        $limit = isset($query['limit']) && is_numeric($query['limit']) ? (int) $query['limit'] : self::DEFAULT_LIMIT;
        $limit = max(1, min(self::MAX_LIMIT, $limit));

        $offset = isset($query['offset']) && is_numeric($query['offset']) ? (int) $query['offset'] : 0;
        $offset = max(0, $offset);

        $result = $this->useCase->execute($limit, $offset);
        $outstanding = $result->outstandingByInvoiceId;

        return $this->json->create([
            'items' => array_map(
                static fn (Invoice $i): array => InvoiceResponse::toArray(
                    $i,
                    null,
                    $i->id !== null ? ($outstanding[$i->id] ?? null) : null,
                ),
                $result->items,
            ),
            'total' => $result->total,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }
}
