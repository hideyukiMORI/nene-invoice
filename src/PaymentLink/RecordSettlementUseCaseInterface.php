<?php

declare(strict_types=1);

namespace NeneInvoice\PaymentLink;

interface RecordSettlementUseCaseInterface
{
    /**
     * Records a confirmed gateway settlement against the payment link it belongs
     * to (idempotent on the gateway charge id). Resolves the link by
     * `$paymentLinkId` (from the event metadata) when given, else by `$chargeId`.
     */
    public function execute(?int $paymentLinkId, string $chargeId, int $amountCents): SettlementOutcome;
}
