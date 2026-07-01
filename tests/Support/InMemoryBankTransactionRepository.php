<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Support;

use Nene2\Http\RequestScopedHolder;
use NeneInvoice\BankTransaction\BankTransaction;
use NeneInvoice\BankTransaction\BankTransactionNotFoundException;
use NeneInvoice\BankTransaction\BankTransactionRepositoryInterface;
use NeneInvoice\BankTransaction\BankTransactionStatus;

/**
 * In-memory fake for use-case tests (no database). `save` forces the org from
 * the request-scoped holder and reads are org-scoped, mirroring
 * {@see \NeneInvoice\BankTransaction\PdoBankTransactionRepository}.
 */
final class InMemoryBankTransactionRepository implements BankTransactionRepositoryInterface
{
    /** @var array<int, BankTransaction> */
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

    public function save(BankTransaction $transaction): int
    {
        $id              = $this->nextId++;
        $this->byId[$id] = $this->copy($transaction, $id, $this->orgId->get());

        return $id;
    }

    public function findById(int $id): ?BankTransaction
    {
        $transaction = $this->byId[$id] ?? null;

        return $transaction !== null && $transaction->organizationId === $this->orgId->get() ? $transaction : null;
    }

    /** @return list<BankTransaction> */
    public function findByOrganization(int $limit, int $offset): array
    {
        return array_slice($this->mine(), $offset, $limit);
    }

    public function countByOrganization(): int
    {
        return count($this->mine());
    }

    /** @return list<BankTransaction> */
    public function findByStatus(?BankTransactionStatus $status, int $limit, int $offset): array
    {
        return array_slice($this->withStatus($status), $offset, $limit);
    }

    public function countByStatus(?BankTransactionStatus $status): int
    {
        return count($this->withStatus($status));
    }

    /** @return list<BankTransaction> newest first, org-scoped, optionally status-filtered */
    private function withStatus(?BankTransactionStatus $status): array
    {
        if ($status === null) {
            return $this->mine();
        }

        return array_values(array_filter(
            $this->mine(),
            static fn (BankTransaction $t): bool => $t->status === $status,
        ));
    }

    public function findByBankReference(string $bankReference): ?BankTransaction
    {
        foreach ($this->mine() as $transaction) {
            if ($transaction->bankReference === $bankReference) {
                return $transaction;
            }
        }

        return null;
    }

    public function update(BankTransaction $transaction): void
    {
        if ($transaction->id === null || $this->findById($transaction->id) === null) {
            throw new BankTransactionNotFoundException($transaction->id ?? 0);
        }

        $this->byId[$transaction->id] = $this->copy($transaction, $transaction->id, $this->orgId->get());
    }

    /** @return list<BankTransaction> newest first (value_date desc, id desc), org-scoped */
    private function mine(): array
    {
        $mine = array_values(array_filter(
            $this->byId,
            fn (BankTransaction $t): bool => $t->organizationId === $this->orgId->get(),
        ));

        usort($mine, static fn (BankTransaction $a, BankTransaction $b): int => [$b->valueDate, $b->id ?? 0] <=> [$a->valueDate, $a->id ?? 0]);

        return $mine;
    }

    private function copy(BankTransaction $transaction, int $id, int $organizationId): BankTransaction
    {
        return new BankTransaction(
            organizationId: $organizationId,
            valueDate: $transaction->valueDate,
            direction: $transaction->direction,
            amountCents: $transaction->amountCents,
            payerName: $transaction->payerName,
            description: $transaction->description,
            bankReference: $transaction->bankReference,
            status: $transaction->status,
            matchedInvoiceId: $transaction->matchedInvoiceId,
            matchedPaymentId: $transaction->matchedPaymentId,
            importedAt: $transaction->importedAt ?? '2026-06-06 00:00:00',
            id: $id,
            createdAt: $transaction->createdAt ?? '2026-06-06 00:00:00',
            updatedAt: '2026-06-06 00:00:00',
        );
    }
}
