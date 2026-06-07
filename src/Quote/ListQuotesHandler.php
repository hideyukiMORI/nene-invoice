<?php

declare(strict_types=1);

namespace NeneInvoice\Quote;

use Nene2\Http\JsonResponseFactory;
use Nene2\Http\PaginationQueryParser;
use Nene2\Http\PaginationResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `GET /admin/quotes` — lists quote headers in the resolved organization
 * (scoped by the repository via the org holder).
 */
final readonly class ListQuotesHandler implements RequestHandlerInterface
{
    public function __construct(
        private ListQuotesUseCaseInterface $useCase,
        private JsonResponseFactory $json,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $pagination = PaginationQueryParser::parse($request);
        $query = $request->getQueryParams();

        $filter = QuoteListFilterFactory::fromQueryParams($query);
        $sort   = QuoteSort::fromInput(self::stringParam($query, 'sort'), self::stringParam($query, 'order'));

        $result      = $this->useCase->executeAdmin($filter, $sort, $pagination->limit, $pagination->offset);
        $clientNames = $result->clientNameByQuoteId;

        return $this->json->create((new PaginationResponse(
            items: array_map(
                static fn (Quote $q): array => QuoteResponse::toArray(
                    $q,
                    null,
                    $q->id !== null ? ($clientNames[$q->id] ?? null) : null,
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
