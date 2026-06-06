<?php

declare(strict_types=1);

namespace NeneInvoice\Payment;

interface VoidPaymentUseCaseInterface
{
    public function execute(?int $actorUserId, int $invoiceId, int $paymentId, ?string $reason): RecordPaymentResult;
}
