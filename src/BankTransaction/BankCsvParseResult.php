<?php

declare(strict_types=1);

namespace NeneInvoice\BankTransaction;

/**
 * Outcome of {@see BankCsvParser::parse()}. Either a whole-file `formatError`
 * (encoding / size / no amount column) with no transactions, or the parsed
 * credit/debit lines plus per-row `errors` for rows that could not be read
 * (e.g. an unparseable date) — the readable rows still come through.
 *
 * @phpstan-type BankCsvRowError array{line: int, reason: string}
 */
final readonly class BankCsvParseResult
{
    /**
     * @param list<BankTransaction>  $transactions
     * @param list<BankCsvRowError>  $errors
     */
    private function __construct(
        public ?string $formatError,
        public array $transactions,
        public array $errors,
    ) {
    }

    public static function rejected(string $reason): self
    {
        return new self($reason, [], []);
    }

    /**
     * @param list<BankTransaction> $transactions
     * @param list<BankCsvRowError> $errors
     */
    public static function parsed(array $transactions, array $errors): self
    {
        return new self(null, $transactions, $errors);
    }
}
