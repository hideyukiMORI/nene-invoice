<?php

declare(strict_types=1);

namespace NeneInvoice\BankTransaction;

/**
 * How a particular bank's CSV columns map onto the canonical bank-line fields
 * (#505). Banks differ in column order, headers, date format, encoding, and
 * whether they expose a single signed amount or separate deposit/withdrawal
 * columns — so reconciliation imports are driven by this mapping rather than a
 * fixed template. Built-in starting points live in {@see BankImportPresets}.
 *
 * Columns are referenced by 0-based index (robust whether or not the file has a
 * header row). Provide **either** `amountColumn` (a signed amount: positive =
 * deposit, negative = withdrawal) **or** `creditColumn`/`debitColumn` (separate
 * 入金/出金 columns).
 */
final readonly class BankCsvColumnMapping
{
    public const ENCODING_UTF8      = 'utf-8';
    public const ENCODING_SHIFT_JIS = 'shift_jis';
    public const ENCODING_AUTO      = 'auto';

    public function __construct(
        public int $valueDateColumn,
        public string $dateFormat = 'Y/m/d',
        public ?int $amountColumn = null,
        public ?int $creditColumn = null,
        public ?int $debitColumn = null,
        public ?int $payerNameColumn = null,
        public ?int $bankReferenceColumn = null,
        public ?int $descriptionColumn = null,
        public bool $hasHeader = true,
        public string $encoding = self::ENCODING_AUTO,
    ) {
    }

    /**
     * Whether at least one amount source is configured. A mapping with no amount
     * column cannot produce transactions and is rejected by the parser.
     */
    public function hasAmountSource(): bool
    {
        return $this->amountColumn !== null || $this->creditColumn !== null || $this->debitColumn !== null;
    }
}
