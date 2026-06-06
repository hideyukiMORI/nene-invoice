<?php

declare(strict_types=1);

namespace NeneInvoice\Item;

/**
 * Serializes an {@see Item} to its snake_case JSON representation.
 */
final class ItemResponse
{
    /** @return array<string, mixed> */
    public static function toArray(Item $item): array
    {
        return [
            'id' => $item->id,
            'organization_id' => $item->organizationId,
            'description' => $item->description,
            'default_unit_price_cents' => $item->defaultUnitPriceCents,
            'default_tax_rate_bps' => $item->defaultTaxRateBps,
            'created_at' => $item->createdAt,
            'updated_at' => $item->updatedAt,
        ];
    }
}
