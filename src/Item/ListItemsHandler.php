<?php

declare(strict_types=1);

namespace NeneInvoice\Item;

use Nene2\Http\JsonResponseFactory;
use Nene2\Http\PaginationQueryParser;
use Nene2\Http\PaginationResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `GET /admin/items` — lists item-master rows in the resolved organization
 * (scoped by the repository via the request-scoped org holder).
 */
final readonly class ListItemsHandler implements RequestHandlerInterface
{
    public function __construct(
        private ListItemsUseCase $useCase,
        private JsonResponseFactory $json,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $pagination = PaginationQueryParser::parse($request);
        $query = $request->getQueryParams();

        $searchValue = $query['q'] ?? null;
        $search      = is_string($searchValue) && trim($searchValue) !== '' ? trim($searchValue) : null;
        $sortValue   = $query['sort'] ?? null;
        $orderValue  = $query['order'] ?? null;
        $sort        = ItemSort::fromInput(
            is_string($sortValue) ? $sortValue : null,
            is_string($orderValue) ? $orderValue : null,
        );

        $result = $this->useCase->executeAdmin(new ItemListFilter($search), $sort, $pagination->limit, $pagination->offset);

        return $this->json->create((new PaginationResponse(
            items: array_map(static fn (Item $i): array => ItemResponse::toArray($i), $result->items),
            limit: $pagination->limit,
            offset: $pagination->offset,
            total: $result->total,
        ))->toArray());
    }
}
