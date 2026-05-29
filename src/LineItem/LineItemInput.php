<?php

declare(strict_types=1);

namespace NeneInvoice\LineItem;

/**
 * One line on a quote or invoice, used as input to tax calculation.
 *
 * Money is integer cents (smallest currency unit); the tax rate is basis points
 * (1000 = 10%, 800 = 8%). The line subtotal is intentionally **not** rounded —
 * rounding happens once per tax rate at the document level (ADR 0004).
 */
final readonly class LineItemInput
{
    public function __construct(
        public string $description,
        public int $quantity,
        public int $unitPriceCents,
        public int $taxRateBps,
    ) {
    }

    public function lineSubtotalCents(): int
    {
        return $this->quantity * $this->unitPriceCents;
    }
}
