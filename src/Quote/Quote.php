<?php

declare(strict_types=1);

namespace NeneInvoice\Quote;

/**
 * A quote (見積書) header. Line items live in `line_items` (polymorphic) and are
 * loaded separately. Totals are integer cents and are the single source of truth
 * computed by the tax calculator (ADR 0004).
 */
final readonly class Quote
{
    public function __construct(
        public int $organizationId,
        public int $clientId,
        public string $quoteNumber,
        public QuoteStatus $status,
        public int $subtotalCents,
        public int $taxCents,
        public int $totalCents,
        public ?string $issuedAt = null,
        public ?string $validUntil = null,
        public ?string $notes = null,
        public bool $isDeleted = false,
        public ?int $id = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
    ) {
    }
}
