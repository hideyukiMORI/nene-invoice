<?php

declare(strict_types=1);

namespace NeneInvoice\Payment;

interface RecordPaymentUseCaseInterface
{
    public function execute(?int $actorUserId, int $invoiceId, RecordPaymentInput $input): RecordPaymentResult;
}
