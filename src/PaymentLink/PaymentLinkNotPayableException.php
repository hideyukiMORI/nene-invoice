<?php

declare(strict_types=1);

namespace NeneInvoice\PaymentLink;

use RuntimeException;

/**
 * Raised when a payment link cannot be paid: unknown token, expired, revoked,
 * already paid, or its invoice has no outstanding balance. Surfaced as 404 to
 * avoid leaking which links exist (oracle), mirroring the download-token route.
 */
final class PaymentLinkNotPayableException extends RuntimeException
{
}
