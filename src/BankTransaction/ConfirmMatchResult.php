<?php

declare(strict_types=1);

namespace NeneInvoice\BankTransaction;

use NeneInvoice\Payment\RecordPaymentResult;

/**
 * Outcome of confirming a bank-line match (#505): the staged transaction advanced
 * to `posted` (now linking `matchedInvoiceId` / `matchedPaymentId`) plus the
 * {@see RecordPaymentResult} for the payment that was recorded — the invoice in
 * its resulting state and the running total paid.
 */
final readonly class ConfirmMatchResult
{
    public function __construct(
        public BankTransaction $transaction,
        public RecordPaymentResult $payment,
    ) {
    }
}
