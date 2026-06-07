<?php

declare(strict_types=1);

namespace NeneInvoice\Client;

/**
 * Builds a {@see ClientListFilter} from raw query parameters. Shared by the list
 * and CSV-export handlers so both interpret the same filter identically — the
 * export reflects exactly what the list shows.
 */
final class ClientListFilterFactory
{
    /**
     * @param array<string, mixed> $query
     */
    public static function fromQueryParams(array $query): ClientListFilter
    {
        $value  = $query['q'] ?? null;
        $search = is_string($value) && trim($value) !== '' ? trim($value) : null;

        return new ClientListFilter($search);
    }
}
