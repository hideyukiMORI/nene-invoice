<?php

declare(strict_types=1);

namespace NeneInvoice\ServiceApi;

use NeneInvoice\Invoice\Invoice;
use NeneInvoice\Payment\Payment;

/**
 * Serializes invoices for the NeNe Clear service surface (ADR 0009 / upstream
 * contract §2). Field names follow the contract read model (`invoice_id`,
 * `outstanding_cents`, `currency`), which differs from the operator
 * `InvoiceResponse`. Money is integer cents; figures are owned by Invoice.
 */
final class ServiceInvoiceResponse
{
    private const CURRENCY = 'JPY';

    /** @return array<string, mixed> */
    public static function listItem(Invoice $invoice, int $outstandingCents): array
    {
        return [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoiceNumber,
            'client_id' => $invoice->clientId,
            'issued_at' => $invoice->issuedAt,
            'due_at' => $invoice->dueAt,
            'total_cents' => $invoice->totalCents,
            'outstanding_cents' => $outstandingCents,
            'status' => $invoice->status->value,
            'currency' => self::CURRENCY,
        ];
    }

    /**
     * @param list<Payment> $payments
     * @return array<string, mixed>
     */
    public static function detail(Invoice $invoice, int $outstandingCents, array $payments): array
    {
        $data = self::listItem($invoice, $outstandingCents);
        $data['payments'] = array_map(static fn (Payment $p): array => [
            'payment_id' => $p->id,
            'amount_cents' => $p->amountCents,
            'paid_at' => $p->paidAt,
            'method' => $p->method,
            // external_reference lands with the write API (ADR 0009 §3); null until then.
            'external_reference' => null,
        ], $payments);

        return $data;
    }
}
