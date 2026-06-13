<?php

declare(strict_types=1);

namespace NeneInvoice\PaymentLink;

interface RevokePaymentLinkUseCaseInterface
{
    public function execute(?int $actorUserId, int $paymentLinkId): RevokeOutcome;
}
