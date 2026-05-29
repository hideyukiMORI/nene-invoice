<?php

declare(strict_types=1);

namespace NeneInvoice\LineItem;

/**
 * Serializes a {@see LineItem} to its snake_case JSON representation.
 *
 * `line_subtotal_cents` is the pre-tax row amount (illustrative). Per-line tax is
 * intentionally omitted — tax is rounded once per rate at the document level
 * (ADR 0004), so a per-line tax figure must never be summed.
 */
final class LineItemResponse
{
    /** @return array<string, mixed> */
    public static function toArray(LineItem $line): array
    {
        return [
            'id' => $line->id,
            'description' => $line->description,
            'quantity' => $line->quantity,
            'unit_price_cents' => $line->unitPriceCents,
            'tax_rate_bps' => $line->taxRateBps,
            'sort_order' => $line->sortOrder,
            'line_subtotal_cents' => $line->lineSubtotalCents(),
        ];
    }
}
