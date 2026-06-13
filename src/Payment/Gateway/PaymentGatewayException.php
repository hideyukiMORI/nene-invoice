<?php

declare(strict_types=1);

namespace NeneInvoice\Payment\Gateway;

use RuntimeException;

/**
 * Raised when a gateway charge cannot be completed (declined card, gateway
 * error, transport failure). The message is safe for logging but MUST NOT carry
 * card data (SAQ-A).
 */
final class PaymentGatewayException extends RuntimeException
{
}
