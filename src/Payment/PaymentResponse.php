<?php

declare(strict_types=1);

namespace NeneInvoice\Payment;

/**
 * Serializes a {@see Payment} to its snake_case JSON representation.
 */
final class PaymentResponse
{
    /** @return array<string, mixed> */
    public static function toArray(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'organization_id' => $payment->organizationId,
            'invoice_id' => $payment->invoiceId,
            'amount_cents' => $payment->amountCents,
            'paid_at' => $payment->paidAt,
            'method' => $payment->method,
            'note' => $payment->note,
            'created_at' => $payment->createdAt,
            'updated_at' => $payment->updatedAt,
        ];
    }
}
