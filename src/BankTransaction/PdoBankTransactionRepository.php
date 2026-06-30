<?php

declare(strict_types=1);

namespace NeneInvoice\BankTransaction;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Http\RequestScopedHolder;

final readonly class PdoBankTransactionRepository implements BankTransactionRepositoryInterface
{
    private const COLUMNS = 'id, organization_id, value_date, direction, amount_cents, payer_name, description, bank_reference, status, matched_invoice_id, matched_payment_id, imported_at, created_at, updated_at';

    /**
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private DatabaseQueryExecutorInterface $query,
        private RequestScopedHolder $orgId,
    ) {
    }

    public function save(BankTransaction $transaction): int
    {
        $now = date('Y-m-d H:i:s');

        // The organization is forced from the request-scoped holder.
        $this->query->execute(
            'INSERT INTO bank_transactions (organization_id, value_date, direction, amount_cents, payer_name, description, bank_reference, status, matched_invoice_id, matched_payment_id, imported_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $this->orgId->get(),
                $transaction->valueDate,
                $transaction->direction->value,
                $transaction->amountCents,
                $transaction->payerName,
                $transaction->description,
                $transaction->bankReference,
                $transaction->status->value,
                $transaction->matchedInvoiceId,
                $transaction->matchedPaymentId,
                $transaction->importedAt ?? $now,
                $now,
                $now,
            ],
        );

        return $this->query->lastInsertId();
    }

    public function findById(int $id): ?BankTransaction
    {
        $row = $this->query->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM bank_transactions WHERE id = ? AND organization_id = ?',
            [$id, $this->orgId->get()],
        );

        return $row !== null ? $this->mapRow($row) : null;
    }

    /** @return list<BankTransaction> */
    public function findByOrganization(int $limit, int $offset): array
    {
        $rows = $this->query->fetchAll(
            'SELECT ' . self::COLUMNS . ' FROM bank_transactions
             WHERE organization_id = ?
             ORDER BY value_date DESC, id DESC LIMIT ? OFFSET ?',
            [$this->orgId->get(), $limit, $offset],
        );

        return array_map(fn (array $row): BankTransaction => $this->mapRow($row), $rows);
    }

    public function countByOrganization(): int
    {
        $row = $this->query->fetchOne(
            'SELECT COUNT(*) AS cnt FROM bank_transactions WHERE organization_id = ?',
            [$this->orgId->get()],
        );

        return $row !== null ? (int) $row['cnt'] : 0;
    }

    public function findByBankReference(string $bankReference): ?BankTransaction
    {
        $row = $this->query->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM bank_transactions
             WHERE organization_id = ? AND bank_reference = ?
             ORDER BY id ASC LIMIT 1',
            [$this->orgId->get(), $bankReference],
        );

        return $row !== null ? $this->mapRow($row) : null;
    }

    public function update(BankTransaction $transaction): void
    {
        if ($transaction->id === null) {
            throw new BankTransactionNotFoundException(0);
        }

        $now = date('Y-m-d H:i:s');

        $affected = $this->query->execute(
            'UPDATE bank_transactions SET value_date = ?, direction = ?, amount_cents = ?, payer_name = ?, description = ?, bank_reference = ?, status = ?, matched_invoice_id = ?, matched_payment_id = ?, updated_at = ?
             WHERE id = ? AND organization_id = ?',
            [
                $transaction->valueDate,
                $transaction->direction->value,
                $transaction->amountCents,
                $transaction->payerName,
                $transaction->description,
                $transaction->bankReference,
                $transaction->status->value,
                $transaction->matchedInvoiceId,
                $transaction->matchedPaymentId,
                $now,
                $transaction->id,
                $this->orgId->get(),
            ],
        );

        if ($affected === 0 && $this->findById($transaction->id) === null) {
            throw new BankTransactionNotFoundException($transaction->id);
        }
    }

    /** @param array<string, mixed> $row */
    private function mapRow(array $row): BankTransaction
    {
        return new BankTransaction(
            organizationId: (int) $row['organization_id'],
            valueDate: (string) $row['value_date'],
            direction: BankTransactionDirection::from((string) $row['direction']),
            amountCents: (int) $row['amount_cents'],
            payerName: isset($row['payer_name']) && $row['payer_name'] !== '' ? (string) $row['payer_name'] : null,
            description: isset($row['description']) && $row['description'] !== '' ? (string) $row['description'] : null,
            bankReference: isset($row['bank_reference']) && $row['bank_reference'] !== '' ? (string) $row['bank_reference'] : null,
            status: BankTransactionStatus::from((string) $row['status']),
            matchedInvoiceId: isset($row['matched_invoice_id']) ? (int) $row['matched_invoice_id'] : null,
            matchedPaymentId: isset($row['matched_payment_id']) ? (int) $row['matched_payment_id'] : null,
            importedAt: (string) $row['imported_at'],
            id: (int) $row['id'],
            createdAt: (string) $row['created_at'],
            updatedAt: (string) $row['updated_at'],
        );
    }
}
