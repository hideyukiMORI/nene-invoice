<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

/**
 * An invoice (請求書) header. `invoiceNumber` is null until the invoice is issued
 * (drafts have no number). `quoteId` links back to the originating quote when
 * converted. Totals are integer cents (single source of truth — ADR 0004).
 */
final readonly class Invoice
{
    public function __construct(
        public int $organizationId,
        public int $clientId,
        public InvoiceStatus $status,
        public int $subtotalCents,
        public int $taxCents,
        public int $totalCents,
        public bool $isQualifiedInvoice = false,
        public ?int $quoteId = null,
        public ?string $invoiceNumber = null,
        public ?string $issuedAt = null,
        public ?string $dueAt = null,
        public ?string $notes = null,
        public bool $isDeleted = false,
        public ?int $id = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
    ) {
    }
}
