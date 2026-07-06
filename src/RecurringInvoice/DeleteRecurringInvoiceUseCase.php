<?php

declare(strict_types=1);

namespace NeneInvoice\RecurringInvoice;

use Closure;
use Nene2\Audit\AuditEvent;
use Nene2\Audit\AuditRecorderFactoryInterface;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\RequestScopedHolder;
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
     * @param RequestScopedHolder<int> $orgId
     */
    public function __construct(
        private RecurringInvoiceRepositoryInterface $recurring,
        private LineItemRepositoryInterface $lineItems,
        private DatabaseTransactionManagerInterface $tx,
        private Closure $recurringFactory,
        private AuditRecorderFactoryInterface $auditFactory,
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
            $this->auditFactory->forExecutor($exec)->record(new AuditEvent(
                action: 'recurring_invoice.deleted',
                entityType: 'recurring_invoice',
                entityId: $id,
                actorId: $actorUserId,
                organizationId: $organizationId,
                before: $before,
                after: null,
            ));
        });
    }
}
