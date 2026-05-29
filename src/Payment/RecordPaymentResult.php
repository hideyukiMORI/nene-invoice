<?php

declare(strict_types=1);

namespace NeneInvoice\Payment;

use NeneInvoice\Invoice\Invoice;

/**
 * The outcome of recording a payment: the stored payment plus the invoice in its
 * resulting state (status may have transitioned to partially_paid / paid) and the
 * running total paid so far.
 */
final readonly class RecordPaymentResult
{
    public function __construct(
        public Payment $payment,
        public Invoice $invoice,
        public int $totalPaidCents,
    ) {
    }
}
