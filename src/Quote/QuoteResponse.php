<?php

declare(strict_types=1);

namespace NeneInvoice\Quote;

use NeneInvoice\LineItem\LineItem;
use NeneInvoice\LineItem\LineItemResponse;

/**
 * Serializes a {@see Quote} to its snake_case JSON representation. When line
 * items are supplied they are nested under `line_items`.
 */
final class QuoteResponse
{
    /**
     * @param list<LineItem>|null $lines
     * @return array<string, mixed>
     */
    public static function toArray(Quote $quote, ?array $lines = null, ?string $clientName = null): array
    {
        $data = [
            'id' => $quote->id,
            'organization_id' => $quote->organizationId,
            'client_id' => $quote->clientId,
            'client_name' => $clientName,
            'quote_number' => $quote->quoteNumber,
            'status' => $quote->status->value,
            'issued_at' => $quote->issuedAt,
            'valid_until' => $quote->validUntil,
            'subtotal_cents' => $quote->subtotalCents,
            'tax_cents' => $quote->taxCents,
            'total_cents' => $quote->totalCents,
            'notes' => $quote->notes,
            'created_at' => $quote->createdAt,
            'updated_at' => $quote->updatedAt,
        ];

        if ($lines !== null) {
            $data['line_items'] = array_map(static fn (LineItem $l): array => LineItemResponse::toArray($l), $lines);
        }

        return $data;
    }
}
