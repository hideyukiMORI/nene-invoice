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
        private ListQuotesUseCase $useCase,
        private JsonResponseFactory $json,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $pagination = PaginationQueryParser::parse($request);
        $query = $request->getQueryParams();

        $filter = $this->buildFilter($query);
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

    /**
     * @param array<string, mixed> $query
     */
    private function buildFilter(array $query): QuoteListFilter
    {
        $statusParam = self::stringParam($query, 'status');
        $statuses    = $statusParam === null
            ? []
            : array_values(array_filter(
                array_map('trim', explode(',', $statusParam)),
                static fn (string $s): bool => in_array($s, ['draft', 'sent', 'accepted', 'rejected', 'expired'], true),
            ));

        return new QuoteListFilter(
            statuses: $statuses,
            search: self::stringParam($query, 'q'),
            validFrom: self::dateParam($query, 'valid_from'),
            validTo: self::dateParam($query, 'valid_to'),
            totalMin: self::intParam($query, 'total_min'),
            totalMax: self::intParam($query, 'total_max'),
        );
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

    /** @param array<string, mixed> $query */
    private static function intParam(array $query, string $key): ?int
    {
        $value = $query[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    /** @param array<string, mixed> $query */
    private static function dateParam(array $query, string $key): ?string
    {
        $value = self::stringParam($query, $key);

        return $value !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }
}
