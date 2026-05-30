<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Support;

use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Payment\Payment;
use NeneInvoice\Payment\PaymentRepositoryInterface;

/**
 * In-memory fake for use-case tests (no database). Reads are scoped to the
 * request-scoped org holder, mirroring {@see \NeneInvoice\Payment\PdoPaymentRepository}.
 * The holder defaults to organization 1 for single-org tests.
 */
final class InMemoryPaymentRepository implements PaymentRepositoryInterface
{
    /** @var array<int, Payment> */
    private array $byId = [];
    private int $nextId = 1;

    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $orgId;

    /** @param RequestScopedHolder<int>|null $orgId */
    public function __construct(?RequestScopedHolder $orgId = null)
    {
        if ($orgId === null) {
            $orgId = new RequestScopedHolder();
            $orgId->set(1);
        }

        $this->orgId = $orgId;
    }

    public function save(Payment $payment): int
    {
        $id = $this->nextId++;
        $this->byId[$id] = new Payment(
            organizationId: $payment->organizationId,
            invoiceId: $payment->invoiceId,
            amountCents: $payment->amountCents,
            paidAt: $payment->paidAt,
            method: $payment->method,
            note: $payment->note,
            externalReference: $payment->externalReference,
            idempotencyKey: $payment->idempotencyKey,
            isDeleted: $payment->isDeleted,
            id: $id,
            createdAt: '2026-05-29 00:00:00',
            updatedAt: '2026-05-29 00:00:00',
        );

        return $id;
    }

    public function findById(int $id): ?Payment
    {
        $payment = $this->byId[$id] ?? null;

        return $payment !== null && $payment->organizationId === $this->orgId->get() ? $payment : null;
    }

    public function findByIdempotencyKey(string $idempotencyKey): ?Payment
    {
        foreach ($this->byId as $payment) {
            if ($payment->organizationId === $this->orgId->get() && $payment->idempotencyKey === $idempotencyKey) {
                return $payment;
            }
        }

        return null;
    }

    public function markVoided(int $id): void
    {
        $existing = $this->byId[$id] ?? null;
        if ($existing === null || $existing->isDeleted) {
            return;
        }

        $this->byId[$id] = new Payment(
            organizationId: $existing->organizationId,
            invoiceId: $existing->invoiceId,
            amountCents: $existing->amountCents,
            paidAt: $existing->paidAt,
            method: $existing->method,
            note: $existing->note,
            externalReference: $existing->externalReference,
            idempotencyKey: $existing->idempotencyKey,
            isDeleted: true,
            id: $existing->id,
            createdAt: $existing->createdAt,
            updatedAt: $existing->updatedAt,
        );
    }

    /** @return list<Payment> */
    public function findByInvoice(int $invoiceId): array
    {
        return array_values(array_filter(
            $this->byId,
            fn (Payment $p): bool => $p->invoiceId === $invoiceId && !$p->isDeleted && $p->organizationId === $this->orgId->get(),
        ));
    }

    public function totalPaidForInvoice(int $invoiceId): int
    {
        $total = 0;

        foreach ($this->findByInvoice($invoiceId) as $payment) {
            $total += $payment->amountCents;
        }

        return $total;
    }

    /**
     * @param list<int> $invoiceIds
     * @return array<int, int>
     */
    public function sumPaidForInvoices(array $invoiceIds): array
    {
        $totals = [];

        foreach ($invoiceIds as $invoiceId) {
            $paid = $this->totalPaidForInvoice($invoiceId);
            if ($paid > 0) {
                $totals[$invoiceId] = $paid;
            }
        }

        return $totals;
    }

    public function outstandingTotal(): int
    {
        // InMemory: invoices are tracked separately, so this fake returns 0.
        // Tests that need a real total use integration (Pdo) setup.
        return 0;
    }
}
