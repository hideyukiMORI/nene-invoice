<?php

declare(strict_types=1);

namespace NeneInvoice\Client;

use Nene2\Http\JsonResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `GET /admin/clients` — lists clients in the resolved organization (scoped by
 * the repository via the request-scoped org holder).
 */
final readonly class ListClientsHandler implements RequestHandlerInterface
{
    private const DEFAULT_LIMIT = 20;
    private const MAX_LIMIT = 100;

    public function __construct(
        private ListClientsUseCase $useCase,
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

        $searchValue = $query['q'] ?? null;
        $search      = is_string($searchValue) && trim($searchValue) !== '' ? trim($searchValue) : null;
        $sortValue   = $query['sort'] ?? null;
        $orderValue  = $query['order'] ?? null;
        $sort        = ClientSort::fromInput(
            is_string($sortValue) ? $sortValue : null,
            is_string($orderValue) ? $orderValue : null,
        );

        $result = $this->useCase->executeAdmin(new ClientListFilter($search), $sort, $limit, $offset);

        return $this->json->create([
            'items' => array_map(static fn (Client $c): array => ClientResponse::toArray($c), $result->items),
            'total' => $result->total,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }
}
