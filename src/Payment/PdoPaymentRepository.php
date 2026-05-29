<?php

declare(strict_types=1);

namespace NeneInvoice\Payment;

use Nene2\Database\DatabaseQueryExecutorInterface;

final readonly class PdoPaymentRepository implements PaymentRepositoryInterface
{
    private const COLUMNS = 'id, organization_id, invoice_id, amount_cents, paid_at, method, note, is_deleted, created_at, updated_at';

    public function __construct(
        private DatabaseQueryExecutorInterface $query,
    ) {
    }

    public function save(Payment $payment): int
    {
        $now = date('Y-m-d H:i:s');

        $this->query->execute(
            'INSERT INTO payments (organization_id, invoice_id, amount_cents, paid_at, method, note, is_deleted, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?)',
            [
                $payment->organizationId,
                $payment->invoiceId,
                $payment->amountCents,
                $payment->paidAt,
                $payment->method,
                $payment->note,
                $now,
                $now,
            ],
        );

        return $this->query->lastInsertId();
    }

    /** @return list<Payment> */
    public function findByInvoice(int $invoiceId): array
    {
        $rows = $this->query->fetchAll(
            'SELECT ' . self::COLUMNS . ' FROM payments WHERE invoice_id = ? AND is_deleted = 0 ORDER BY paid_at ASC, id ASC',
            [$invoiceId],
        );

        return array_map(fn (array $row): Payment => $this->mapRow($row), $rows);
    }

    public function totalPaidForInvoice(int $invoiceId): int
    {
        $row = $this->query->fetchOne(
            'SELECT COALESCE(SUM(amount_cents), 0) AS total FROM payments WHERE invoice_id = ? AND is_deleted = 0',
            [$invoiceId],
        );

        return $row !== null ? (int) $row['total'] : 0;
    }

    /** @param array<string, mixed> $row */
    private function mapRow(array $row): Payment
    {
        return new Payment(
            organizationId: (int) $row['organization_id'],
            invoiceId: (int) $row['invoice_id'],
            amountCents: (int) $row['amount_cents'],
            paidAt: (string) $row['paid_at'],
            method: isset($row['method']) && $row['method'] !== '' ? (string) $row['method'] : null,
            note: isset($row['note']) && $row['note'] !== '' ? (string) $row['note'] : null,
            isDeleted: (bool) $row['is_deleted'],
            id: (int) $row['id'],
            createdAt: (string) $row['created_at'],
            updatedAt: (string) $row['updated_at'],
        );
    }
}
