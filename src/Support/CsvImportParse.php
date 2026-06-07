<?php

declare(strict_types=1);

namespace NeneInvoice\Support;

/**
 * Outcome of {@see CsvImport::parse()}. Either a file-level `formatError` (the
 * format gate rejected the whole file — encoding / header / size) with no rows,
 * or the parsed data rows (header excluded) for per-row domain validation.
 *
 * @phpstan-type CsvImportRow array{line: int, cells: array<string, string>, well_formed: bool}
 */
final readonly class CsvImportParse
{
    /**
     * @param list<CsvImportRow> $rows
     */
    private function __construct(
        public ?string $formatError,
        public array $rows,
    ) {
    }

    public static function rejected(string $reason): self
    {
        return new self($reason, []);
    }

    /**
     * @param list<CsvImportRow> $rows
     */
    public static function accepted(array $rows): self
    {
        return new self(null, $rows);
    }
}
