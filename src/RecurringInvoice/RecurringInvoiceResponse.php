<?php

declare(strict_types=1);

namespace NeneInvoice\RecurringInvoice;

use NeneInvoice\LineItem\LineItem;
use NeneInvoice\LineItem\LineItemResponse;

/**
 * Serializes a {@see RecurringInvoice} to its snake_case JSON representation.
 * When line items are supplied they are nested under `line_items`.
 */
final class RecurringInvoiceResponse
{
    /**
     * @param list<LineItem>|null $lines
     * @return array<string, mixed>
     */
    public static function toArray(RecurringInvoice $schedule, ?array $lines = null, ?string $clientName = null): array
    {
        $data = [
            'id' => $schedule->id,
            'organization_id' => $schedule->organizationId,
            'client_id' => $schedule->clientId,
            'client_name' => $clientName,
            'name' => $schedule->name,
            'frequency' => $schedule->frequency->value,
            'subtotal_cents' => $schedule->subtotalCents,
            'tax_cents' => $schedule->taxCents,
            'total_cents' => $schedule->totalCents,
            'next_run_on' => $schedule->nextRunOn,
            'last_run_on' => $schedule->lastRunOn,
            'is_active' => $schedule->isActive,
            'notes' => $schedule->notes,
            'created_at' => $schedule->createdAt,
            'updated_at' => $schedule->updatedAt,
        ];

        if ($lines !== null) {
            $data['line_items'] = array_map(static fn (LineItem $l): array => LineItemResponse::toArray($l), $lines);
        }

        return $data;
    }
}
