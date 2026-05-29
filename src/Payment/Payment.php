<?php

declare(strict_types=1);

namespace NeneInvoice\Payment;

/**
 * A payment (入金) received against an issued invoice. `amountCents` is integer
 * cents (smallest currency unit; ADR 0004 — no floats). One invoice may have many
 * payments; their sum drives the invoice's paid / partially_paid status.
 */
final readonly class Payment
{
    public function __construct(
        public int $organizationId,
        public int $invoiceId,
        public int $amountCents,
        public string $paidAt,
        public ?string $method = null,
        public ?string $note = null,
        /** Originating system's reconciliation id (e.g. NeNe Clear) — ADR 0009. */
        public ?string $externalReference = null,
        /** Idempotency key for safe retried writes; null for manual payments. */
        public ?string $idempotencyKey = null,
        public bool $isDeleted = false,
        public ?int $id = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
    ) {
    }
}
