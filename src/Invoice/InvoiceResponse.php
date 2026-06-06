<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

use NeneInvoice\LineItem\LineItem;
use NeneInvoice\LineItem\LineItemResponse;
use NeneInvoice\Support\Jst;

/**
 * Serializes an {@see Invoice} to its snake_case JSON representation. Line items
 * are nested under `line_items` when supplied.
 */
final class InvoiceResponse
{
    /**
     * @param list<LineItem>|null $lines
     * @return array<string, mixed>
     */
    public static function toArray(
        Invoice $invoice,
        ?array $lines = null,
        ?int $outstandingCents = null,
        ?string $clientName = null,
    ): array {
        $data = [
            'id' => $invoice->id,
            'organization_id' => $invoice->organizationId,
            'client_id' => $invoice->clientId,
            'client_name' => $clientName,
            'quote_id' => $invoice->quoteId,
            'invoice_number' => $invoice->invoiceNumber,
            'status' => $invoice->status->value,
            'is_overdue' => self::computeIsOverdue($invoice),
            'is_qualified_invoice' => $invoice->isQualifiedInvoice,
            'issued_at' => $invoice->issuedAt,
            'due_at' => $invoice->dueAt,
            'subtotal_cents' => $invoice->subtotalCents,
            'tax_cents' => $invoice->taxCents,
            'total_cents' => $invoice->totalCents,
            'notes' => $invoice->notes,
            'created_at' => $invoice->createdAt,
            'updated_at' => $invoice->updatedAt,
        ];

        if ($outstandingCents !== null) {
            $data['outstanding_cents'] = $outstandingCents;
        }

        if ($lines !== null) {
            $data['line_items'] = array_map(static fn (LineItem $l): array => LineItemResponse::toArray($l), $lines);
        }

        return $data;
    }

    private static function computeIsOverdue(Invoice $invoice): bool
    {
        if ($invoice->status !== InvoiceStatus::Issued && $invoice->status !== InvoiceStatus::PartiallyPaid) {
            return false;
        }

        if ($invoice->dueAt === null) {
            return false;
        }

        return $invoice->dueAt < Jst::nowString();
    }
}
