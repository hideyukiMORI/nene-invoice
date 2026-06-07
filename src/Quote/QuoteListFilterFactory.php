<?php

declare(strict_types=1);

namespace NeneInvoice\Quote;

/**
 * Builds a {@see QuoteListFilter} from raw query parameters. Shared by the list
 * and CSV-export handlers so both interpret the same filters identically — the
 * export reflects exactly what the list shows.
 */
final class QuoteListFilterFactory
{
    private const STATUSES = ['draft', 'sent', 'accepted', 'rejected', 'expired'];

    /**
     * @param array<string, mixed> $query
     */
    public static function fromQueryParams(array $query): QuoteListFilter
    {
        $statusParam = self::stringParam($query, 'status');
        $statuses    = $statusParam === null
            ? []
            : array_values(array_filter(
                array_map('trim', explode(',', $statusParam)),
                static fn (string $s): bool => in_array($s, self::STATUSES, true),
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
