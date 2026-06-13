<?php

declare(strict_types=1);

namespace NeneInvoice\PaymentLink;

/** Outcome of a successful payment-link charge. */
final readonly class ChargePaymentLinkResult
{
    public function __construct(
        public int $paymentLinkId,
        public int $invoiceId,
        public string $chargeId,
        public int $amountCents,
        /** Resulting invoice status: `paid` or `partially_paid`. */
        public string $invoiceStatus,
    ) {
    }
}
