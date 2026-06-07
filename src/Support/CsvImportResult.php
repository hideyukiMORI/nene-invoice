<?php

declare(strict_types=1);

namespace NeneInvoice\Support;

/**
 * Outcome of a template-only CSV import (ADR 0011), shared across domains
 * (clients, items). Either the file was rejected — a file-level `formatError` or
 * one/more row errors — in which case nothing was written, or it was accepted
 * (applied, or validated under dry-run) with created/updated counts. `accepted`
 * drives the HTTP status (200 vs 422).
 *
 * @phpstan-type RowError array{row: int, column: string|null, code: string, message: string}
 */
final readonly class CsvImportResult
{
    /**
     * @param list<RowError> $errors
     */
    private function __construct(
        public bool $accepted,
        public bool $dryRun,
        public int $rows,
        public int $created,
        public int $updated,
        public ?string $formatError,
        public array $errors,
    ) {
    }

    public static function formatRejected(string $reason): self
    {
        return new self(false, false, 0, 0, 0, $reason, []);
    }

    /**
     * @param list<RowError> $errors
     */
    public static function rowsRejected(int $rows, array $errors): self
    {
        return new self(false, false, $rows, 0, 0, null, $errors);
    }

    public static function applied(int $rows, int $created, int $updated, bool $dryRun): self
    {
        return new self(true, $dryRun, $rows, $created, $updated, null, []);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'accepted'     => $this->accepted,
            'dry_run'      => $this->dryRun,
            'format_error' => $this->formatError,
            'summary'      => [
                'rows'    => $this->rows,
                'created' => $this->created,
                'updated' => $this->updated,
                'errors'  => count($this->errors),
            ],
            'errors' => $this->errors,
        ];
    }
}
