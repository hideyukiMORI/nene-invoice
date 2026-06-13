<?php

declare(strict_types=1);

namespace NeneInvoice\Payment\Gateway;

/**
 * A request to create a card charge on a payment gateway. `cardToken` is a
 * gateway-issued, single-use token produced by the gateway's hosted card form
 * (e.g. PAY.JP Checkout) — the raw PAN never reaches this application (SAQ-A).
 *
 * `metadata` carries our own references (e.g. payment_link_id, invoice_id) so a
 * later webhook can resolve the charge back to the originating link.
 */
final readonly class GatewayChargeRequest
{
    /**
     * @param array<string, string> $metadata
     */
    public function __construct(
        public int $amountCents,
        public string $currency,
        public string $cardToken,
        public array $metadata = [],
    ) {
    }
}
