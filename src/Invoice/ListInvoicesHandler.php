<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

use Nene2\Http\JsonResponseFactory;
use Nene2\Http\PaginationQueryParser;
use Nene2\Http\PaginationResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `GET /admin/invoices` — lists invoice headers in the resolved organization
 * (scoped by the repository via the org holder).
 */
final readonly class ListInvoicesHandler implements RequestHandlerInterface
{
    public function __construct(
        private ListInvoicesUseCaseInterface $useCase,
        private JsonResponseFactory $json,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $pagination = PaginationQueryParser::parse($request);
        $query = $request->getQueryParams();

        $filter = InvoiceListFilterFactory::fromQueryParams($query);
        $sort   = InvoiceSort::fromInput(
            self::stringParam($query, 'sort'),
            self::stringParam($query, 'order'),
        );

        $result      = $this->useCase->executeAdmin($filter, $sort, $pagination->limit, $pagination->offset);
        $outstanding = $result->outstandingByInvoiceId;
        $clientNames = $result->clientNameByInvoiceId;

        return $this->json->create((new PaginationResponse(
            items: array_map(
                static fn (Invoice $i): array => InvoiceResponse::toArray(
                    $i,
                    null,
                    $i->id !== null ? ($outstanding[$i->id] ?? null) : null,
                    $i->id !== null ? ($clientNames[$i->id] ?? null) : null,
                ),
                $result->items,
            ),
            limit: $pagination->limit,
            offset: $pagination->offset,
            total: $result->total,
        ))->toArray());
    }

    /** @param array<string, mixed> $query */
    private static function stringParam(array $query, string $key): ?string
    {
        $value = $query[$key] ?? null;
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
