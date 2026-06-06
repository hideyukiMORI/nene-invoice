<?php

declare(strict_types=1);

namespace NeneInvoice\LineItem;

/**
 * Serializes {@see LineItemSuggestion} to the snake_case JSON shape returned by
 * `GET /admin/line-items/suggestions`.
 */
final readonly class LineItemSuggestionResponse
{
    /** @return array{description: string, unit_price_cents: int, tax_rate_bps: int, usage_count: int} */
    public static function toArray(LineItemSuggestion $suggestion): array
    {
        return [
            'description'      => $suggestion->description,
            'unit_price_cents' => $suggestion->unitPriceCents,
            'tax_rate_bps'     => $suggestion->taxRateBps,
            'usage_count'      => $suggestion->usageCount,
        ];
    }
}
