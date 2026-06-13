<?php

declare(strict_types=1);

namespace NeneInvoice\Payment\Gateway;

/**
 * The result of a successful gateway charge. `id` is the gateway charge
 * identifier (e.g. PAY.JP `ch_…`), used as both the payment `external_reference`
 * and the cross-path idempotency key (the synchronous charge and the later
 * webhook must use the *same* charge id so they de-duplicate).
 */
final readonly class GatewayCharge
{
    public function __construct(
        public string $id,
        public bool $paid,
        public int $amountCents,
        public string $currency,
    ) {
    }
}
