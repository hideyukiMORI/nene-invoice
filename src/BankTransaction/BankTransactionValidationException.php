<?php

declare(strict_types=1);

namespace NeneInvoice\BankTransaction;

use DomainException;

/**
 * Thrown when a reconciliation action is not allowed for a staged bank line (#505)
 * — e.g. confirming a `debit` line, re-confirming a line already reconciled to a
 * different invoice, or ignoring a line whose payment is already posted. Surfaces
 * as 422.
 *
 * This is distinct from over-payment: recording the deposit reuses
 * {@see \NeneInvoice\Payment\RecordPaymentUseCase}, whose own guards
 * (over-allocation, draft invoice, future date) surface through the Payment
 * exception handlers.
 */
final class BankTransactionValidationException extends DomainException
{
}
