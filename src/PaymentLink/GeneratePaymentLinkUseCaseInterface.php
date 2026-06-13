<?php

declare(strict_types=1);

namespace NeneInvoice\PaymentLink;

interface GeneratePaymentLinkUseCaseInterface
{
    /** @return array{rawToken: string, expiresAt: string, paymentLinkId: int} */
    public function execute(?int $actorUserId, int $invoiceId): array;
}
