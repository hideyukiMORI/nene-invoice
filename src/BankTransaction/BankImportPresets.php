<?php

declare(strict_types=1);

namespace NeneInvoice\BankTransaction;

/**
 * Built-in column-mapping starting points for common Japanese bank CSV shapes
 * (#505). These are sensible defaults an operator adjusts per bank — not
 * authoritative specifications. The column-mapping engine ({@see BankCsvParser})
 * is the real mechanism; presets just pre-fill the indices.
 */
final class BankImportPresets
{
    /**
     * Separate deposit/withdrawal columns with a header row — the common net-bank
     * statement shape (e.g. `日付,内容,お支払金額,お預り金額,残高`). Shift_JIS by
     * default, as most Japanese banks export. Adjust the indices to the bank.
     */
    public static function netBankCreditDebit(): BankCsvColumnMapping
    {
        return new BankCsvColumnMapping(
            valueDateColumn: 0,
            dateFormat: 'Y/m/d',
            debitColumn: 2,
            creditColumn: 3,
            payerNameColumn: 1,
            descriptionColumn: 1,
            hasHeader: true,
            encoding: BankCsvColumnMapping::ENCODING_AUTO,
        );
    }

    /**
     * A single signed amount column (positive = deposit, negative = withdrawal)
     * with a header row — e.g. `日付,金額,依頼人,摘要`.
     */
    public static function signedAmount(): BankCsvColumnMapping
    {
        return new BankCsvColumnMapping(
            valueDateColumn: 0,
            dateFormat: 'Y-m-d',
            amountColumn: 1,
            payerNameColumn: 2,
            descriptionColumn: 3,
            hasHeader: true,
            encoding: BankCsvColumnMapping::ENCODING_AUTO,
        );
    }
}
