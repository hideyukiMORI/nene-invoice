<?php

declare(strict_types=1);

namespace NeneInvoice\Support;

/**
 * CSV row writer that neutralizes spreadsheet formula injection. Any string cell
 * beginning with a formula trigger (`=`, `+`, `-`, `@`, TAB, CR) is prefixed
 * with a single quote so Excel / LibreOffice / Google Sheets treat it as text
 * rather than executing it. Non-string cells (ints for money etc.) pass through
 * untouched, so numeric columns stay numeric. Use this instead of `fputcsv`
 * directly for every export.
 */
final class Csv
{
    private const FORMULA_TRIGGERS = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * Writes one sanitized CSV row to the stream.
     *
     * @param resource             $handle
     * @param list<string|int|null> $fields
     */
    public static function putRow($handle, array $fields): void
    {
        $sanitized = array_map(
            static fn (string|int|null $field): string|int|null => is_string($field) ? self::neutralize($field) : $field,
            $fields,
        );

        fputcsv($handle, $sanitized);
    }

    /** Prefixes a single quote when the value would be parsed as a formula. */
    private static function neutralize(string $value): string
    {
        if ($value !== '' && in_array($value[0], self::FORMULA_TRIGGERS, true)) {
            return "'" . $value;
        }

        return $value;
    }
}
