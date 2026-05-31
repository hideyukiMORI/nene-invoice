<?php

declare(strict_types=1);

namespace NeneInvoice\Payment;

/**
 * Persistence for payments. Every query is scoped to the organization held in
 * the request-scoped org holder (ADR 0006). Payments are immaterial of their own
 * lifecycle — a correction is a separate (future) refund operation.
 */
interface PaymentRepositoryInterface
{
    public function save(Payment $payment): int;

    public function findById(int $id): ?Payment;

    /** Returns the payment previously recorded with this idempotency key, if any. */
    public function findByIdempotencyKey(string $idempotencyKey): ?Payment;

    /** Voids a payment (soft delete). Idempotent: voiding an already-voided one is a no-op. */
    public function markVoided(int $id): void;

    /** @return list<Payment> */
    public function findByInvoice(int $invoiceId): array;

    /**
     * Sum of all (non-deleted) payment amounts recorded against the invoice, in cents.
     */
    public function totalPaidForInvoice(int $invoiceId): int;

    /**
     * Batch sum of (non-deleted) payments per invoice, in cents. Avoids N+1 when
     * computing outstanding balances over a list.
     *
     * @param list<int> $invoiceIds
     * @return array<int, int> invoice_id => paid cents (invoices with no payments are omitted)
     */
    public function sumPaidForInvoices(array $invoiceIds): array;

    /**
     * Total outstanding balance across all issued / partially_paid invoices for the
     * resolved organization: sum(invoice.total_cents) - sum(non-void payments).
     */
    public function outstandingTotal(): int;

    /**
     * Returns all non-voided payments for the organization, joined with invoice
     * number and client name. Intended for CSV export only.
     *
     * @return list<array{invoice_number: string, client_name: string, paid_at: string, amount_cents: int, method: string, note: string}>
     */
    public function findValidForExport(): array;
}
