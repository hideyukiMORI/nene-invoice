<?php

declare(strict_types=1);

namespace NeneInvoice\BankTransaction;

/**
 * Outcome of {@see ImportBankTransactionsUseCase::execute()}.
 *
 * `formatError` (when non-null) means the whole file was rejected and nothing
 * was imported. Otherwise `importedCount` rows were staged, `skippedDuplicateCount`
 * were skipped because their `bank_reference` was already imported (idempotent
 * re-import), and `rowErrors` lists rows that could not be parsed.
 *
 * @phpstan-type BankCsvRowError array{line: int, reason: string}
 */
final readonly class ImportBankTransactionsResult
{
    /**
     * @param list<BankCsvRowError> $rowErrors
     */
    public function __construct(
        public int $importedCount,
        public int $skippedDuplicateCount,
        public array $rowErrors,
        public ?string $formatError,
    ) {
    }
}
