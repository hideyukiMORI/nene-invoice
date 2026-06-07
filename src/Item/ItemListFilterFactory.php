<?php

declare(strict_types=1);

namespace NeneInvoice\Item;

/**
 * Builds an {@see ItemListFilter} from raw query parameters. Shared by the list
 * and CSV-export handlers so both interpret the same filter identically.
 */
final class ItemListFilterFactory
{
    /**
     * @param array<string, mixed> $query
     */
    public static function fromQueryParams(array $query): ItemListFilter
    {
        $value  = $query['q'] ?? null;
        $search = is_string($value) && trim($value) !== '' ? trim($value) : null;

        return new ItemListFilter($search);
    }
}
