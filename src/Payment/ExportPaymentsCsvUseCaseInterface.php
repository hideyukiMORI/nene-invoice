<?php

declare(strict_types=1);

namespace NeneInvoice\Payment;

interface ExportPaymentsCsvUseCaseInterface
{
    public function execute(): string;
}
