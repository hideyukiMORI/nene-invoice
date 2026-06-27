<?php

declare(strict_types=1);

namespace NeneInvoice\RecurringInvoice;

use Closure;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Audit\AuditRecorderInterface;
use NeneInvoice\LineItem\LineItemParent;
use NeneInvoice\LineItem\LineItemRepositoryInterface;

/**
 * Soft-deletes a recurring schedule (the line template rows are left in place;
 * the soft-deleted header hides them). The delete and its audit commit together.
 */
final readonly class DeleteRecurringInvoiceUseCase
{
    /**
     * @param Closure(DatabaseQueryExecutorInterface): RecurringInvoiceRepositoryInterface $recurringFactory
     * @param Closure(DatabaseQueryExecutorInterface): AuditRecorderInterface $auditFactory
     * @param RequestScopedHolder<int> $orgId
     */
    public function __construct(
        private RecurringInvoiceRepositoryInterface $recurring,
        private LineItemRepositoryInterface $lineItems,
        private DatabaseTransactionManagerInterface $tx,
        private Closure $recurringFactory,
        private Closure $auditFactory,
        private RequestScopedHolder $orgId,
    ) {
    }

    /** @throws RecurringInvoiceNotFoundException */
    public function execute(?int $actorUserId, int $id): void
    {
        $organizationId = $this->orgId->get();

        $existing = $this->recurring->findById($id);
        if ($existing === null) {
            throw new RecurringInvoiceNotFoundException($id);
        }

        $before = RecurringInvoiceResponse::toArray(
            $existing,
            $this->lineItems->findByParent(LineItemParent::RecurringInvoice, $id),
        );

        $this->tx->transactional(function (DatabaseQueryExecutorInterface $exec) use ($actorUserId, $organizationId, $id, $before): void {
            ($this->recurringFactory)($exec)->delete($id);
            ($this->auditFactory)($exec)->record($actorUserId, $organizationId, 'recurring_invoice.deleted', 'recurring_invoice', $id, $before, null);
        });
    }
}
