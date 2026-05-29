<?php

declare(strict_types=1);

namespace NeneInvoice\Payment;

final readonly class RecordPaymentInput
{
    public function __construct(
        public int $amountCents,
        public ?string $paidAt = null,
        public ?string $method = null,
        public ?string $note = null,
    ) {
    }
}
