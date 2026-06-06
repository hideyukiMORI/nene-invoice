<?php

declare(strict_types=1);

namespace NeneInvoice\Payment;

interface ListPaymentsUseCaseInterface
{
    /** @return list<Payment> */
    public function execute(int $invoiceId): array;
}
