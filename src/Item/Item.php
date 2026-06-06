<?php

declare(strict_types=1);

namespace NeneInvoice\Item;

/**
 * An item master row (品目), scoped to one organization (#323). A reusable
 * description with a default unit price and tax rate to seed document lines.
 *
 * The defaults are conveniences only — they never override the tax that applies
 * to a given sale; the operator can edit price and rate per line. Money is
 * integer cents; tax rate is basis points. Items are soft-deleted so historic
 * suggestions/usage stay coherent.
 */
final readonly class Item
{
    public function __construct(
        public int $organizationId,
        public string $description,
        public int $defaultUnitPriceCents,
        public int $defaultTaxRateBps,
        public bool $isDeleted = false,
        public ?int $id = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
    ) {
    }
}
