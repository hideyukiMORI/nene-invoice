<?php

declare(strict_types=1);

namespace NeneInvoice\BankTransaction;

/**
 * Reconciliation state of an imported bank line (#505).
 *
 * - `unmatched`: imported, not yet linked to an invoice (the default).
 * - `matched`: an operator linked it to an invoice but no payment is recorded yet.
 * - `posted`: a payment was recorded from it (links `matched_payment_id`).
 * - `ignored`: an operator dismissed it (fees, non-AR transfers, duplicates).
 *
 * Posting a payment is a separate, compliance-reviewed step (accounting-compliance.md);
 * importing only ever produces `unmatched` rows.
 */
enum BankTransactionStatus: string
{
    case Unmatched = 'unmatched';
    case Matched   = 'matched';
    case Posted    = 'posted';
    case Ignored   = 'ignored';
}
