<?php

declare(strict_types=1);

namespace NeneInvoice\Item;

final readonly class UpdateItemInput
{
    public function __construct(
        public string $description,
        public int $defaultUnitPriceCents,
        public int $defaultTaxRateBps,
    ) {
    }
}
