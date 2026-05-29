<?php

declare(strict_types=1);

namespace NeneInvoice\Payment;

use DomainException;

/**
 * Thrown for payment operations that are not allowed: a non-positive amount, a
 * payment against a draft / fully-paid invoice, or one that would exceed the
 * outstanding balance (over-payment). Surfaces as 422.
 */
final class PaymentValidationException extends DomainException
{
}
