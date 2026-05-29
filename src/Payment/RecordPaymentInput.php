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
        /** Originating system's reconciliation id (NeNe Clear) — ADR 0009. */
        public ?string $externalReference = null,
        /** Idempotency key; a retried write with the same key returns the same payment. */
        public ?string $idempotencyKey = null,
    ) {
    }
}
