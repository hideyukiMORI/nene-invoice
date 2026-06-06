<?php

declare(strict_types=1);

namespace NeneInvoice\Item;

/**
 * Validated item write payload (description + defaults), produced by
 * {@see ItemField::parse()} and adapted into create/update inputs by handlers.
 */
final readonly class ItemWriteValues
{
    public function __construct(
        public string $description,
        public int $defaultUnitPriceCents,
        public int $defaultTaxRateBps,
    ) {
    }
}
