<?php

declare(strict_types=1);

namespace NeneInvoice\BankTransaction;

/**
 * Direction of an imported bank line (#505). Receivable reconciliation (消込)
 * matches `credit` (入金) lines; `debit` lines are imported for completeness but
 * are not candidates for matching against invoices.
 */
enum BankTransactionDirection: string
{
    case Credit = 'credit';
    case Debit  = 'debit';
}
