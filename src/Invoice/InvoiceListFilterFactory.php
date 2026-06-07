<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

/**
 * Builds an {@see InvoiceListFilter} from raw query parameters. Shared by the
 * list and CSV-export handlers so both interpret the same filters identically
 * — the export reflects exactly what the list shows.
 */
final class InvoiceListFilterFactory
{
    private const STATUSES = ['draft', 'issued', 'partially_paid', 'paid'];

    /**
     * @param array<string, mixed> $query
     */
    public static function fromQueryParams(array $query): InvoiceListFilter
    {
        $statusParam = self::stringParam($query, 'status');
        $statuses    = $statusParam === null
            ? []
            : array_values(array_filter(
                array_map('trim', explode(',', $statusParam)),
                static fn (string $s): bool => in_array($s, self::STATUSES, true),
            ));

        return new InvoiceListFilter(
            statuses: $statuses,
            overdueOnly: self::stringParam($query, 'overdue') === '1',
            search: self::stringParam($query, 'q'),
            totalMin: self::intParam($query, 'total_min'),
            totalMax: self::intParam($query, 'total_max'),
            dueFrom: self::dateParam($query, 'due_from'),
            dueTo: self::dateParam($query, 'due_to'),
            issuedFrom: self::dateParam($query, 'issued_from'),
            issuedTo: self::dateParam($query, 'issued_to'),
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
