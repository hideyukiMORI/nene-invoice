<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Support;

use NeneInvoice\Payment\Payment;
use NeneInvoice\Payment\PaymentRepositoryInterface;

/**
 * In-memory fake for use-case tests (no database).
 */
final class InMemoryPaymentRepository implements PaymentRepositoryInterface
{
    /** @var array<int, Payment> */
    private array $byId = [];
    private int $nextId = 1;

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
            isDeleted: $payment->isDeleted,
            id: $id,
            createdAt: '2026-05-29 00:00:00',
            updatedAt: '2026-05-29 00:00:00',
        );

        return $id;
    }

    /** @return list<Payment> */
    public function findByInvoice(int $invoiceId): array
    {
        return array_values(array_filter(
            $this->byId,
            static fn (Payment $p): bool => $p->invoiceId === $invoiceId && !$p->isDeleted,
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
}
