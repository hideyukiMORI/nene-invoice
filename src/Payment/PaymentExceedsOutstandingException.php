<?php

declare(strict_types=1);

namespace NeneInvoice\Payment;

use DomainException;

/**
 * Thrown when a payment would exceed the invoice's outstanding balance
 * (over-allocation). Surfaces as 422 `payment-exceeds-outstanding`, carrying the
 * current outstanding so the caller (e.g. NeNe Clear) can split and record the
 * remainder as client credit — ADR 0009 §3.1.5.
 */
final class PaymentExceedsOutstandingException extends DomainException
{
    public function __construct(public readonly int $outstandingCents)
    {
        parent::__construct('The payment would exceed the invoice outstanding balance.');
    }
}
