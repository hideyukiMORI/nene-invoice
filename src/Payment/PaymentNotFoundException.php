<?php

declare(strict_types=1);

namespace NeneInvoice\Payment;

use DomainException;

/**
 * Thrown when a payment id (or external reference) is not found in the caller's
 * organization / invoice. Surfaces as 404 `payment-not-found`.
 */
final class PaymentNotFoundException extends DomainException
{
    public function __construct(public readonly int $paymentId)
    {
        parent::__construct(sprintf('Payment %d was not found.', $paymentId));
    }
}
