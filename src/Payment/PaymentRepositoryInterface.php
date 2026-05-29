<?php

declare(strict_types=1);

namespace NeneInvoice\Payment;

/**
 * Persistence for payments. Payments are immaterial of their own lifecycle — a
 * correction is a separate (future) refund operation, not an in-place edit.
 */
interface PaymentRepositoryInterface
{
    public function save(Payment $payment): int;

    public function findById(int $id): ?Payment;

    /** Returns the payment previously recorded with this idempotency key, if any. */
    public function findByIdempotencyKey(int $organizationId, string $idempotencyKey): ?Payment;

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
}
