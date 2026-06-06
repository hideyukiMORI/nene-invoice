<?php

declare(strict_types=1);

namespace NeneInvoice\Template;

use NeneInvoice\LineItem\LineItem;

/**
 * Serializes a {@see Template} (with its line presets) to snake_case JSON.
 */
final class TemplateResponse
{
    /**
     * @param list<LineItem> $lines
     *
     * @return array<string, mixed>
     */
    public static function toArray(Template $template, array $lines): array
    {
        return [
            'id' => $template->id,
            'organization_id' => $template->organizationId,
            'name' => $template->name,
            'notes' => $template->notes,
            'line_items' => array_map(
                static fn (LineItem $l): array => [
                    'id' => $l->id,
                    'description' => $l->description,
                    'quantity' => $l->quantity,
                    'unit_price_cents' => $l->unitPriceCents,
                    'tax_rate_bps' => $l->taxRateBps,
                    'sort_order' => $l->sortOrder,
                ],
                $lines,
            ),
            'created_at' => $template->createdAt,
            'updated_at' => $template->updatedAt,
        ];
    }
}
