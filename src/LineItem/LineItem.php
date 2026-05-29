<?php

declare(strict_types=1);

namespace NeneInvoice\LineItem;

/**
 * A persisted line on a quote or invoice. The parent is polymorphic
 * (`parentType` + `parentId`). Money is integer cents; tax rate is basis points.
 */
final readonly class LineItem
{
    public function __construct(
        public LineItemParent $parentType,
        public int $parentId,
        public string $description,
        public int $quantity,
        public int $unitPriceCents,
        public int $taxRateBps,
        public int $sortOrder = 0,
        public ?int $id = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
    ) {
    }

    public function lineSubtotalCents(): int
    {
        return $this->quantity * $this->unitPriceCents;
    }

    public function toCalculationInput(): LineItemInput
    {
        return new LineItemInput($this->description, $this->quantity, $this->unitPriceCents, $this->taxRateBps);
    }
}
