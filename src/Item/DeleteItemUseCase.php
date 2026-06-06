<?php

declare(strict_types=1);

namespace NeneInvoice\Item;

use Closure;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Audit\AuditRecorderInterface;

final readonly class DeleteItemUseCase implements DeleteItemUseCaseInterface
{
    /**
     * @param Closure(DatabaseQueryExecutorInterface): ItemRepositoryInterface $itemsFactory
     * @param Closure(DatabaseQueryExecutorInterface): AuditRecorderInterface $auditFactory
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private ItemRepositoryInterface $items,
        private DatabaseTransactionManagerInterface $tx,
        private Closure $itemsFactory,
        private Closure $auditFactory,
        private RequestScopedHolder $orgId,
    ) {
    }

    /**
     * Soft-deletes an item in the resolved organization. The repository scopes
     * the lookup/delete to the request org, so cross-organization targets
     * surface as not found. The delete and its audit record commit atomically
     * (Issue #352).
     *
     * @throws ItemNotFoundException
     */
    public function execute(?int $actorUserId, int $id): void
    {
        $existing = $this->items->findById($id);

        if ($existing === null) {
            throw new ItemNotFoundException($id);
        }

        $organizationId = $this->orgId->get();

        $this->tx->transactional(function (DatabaseQueryExecutorInterface $exec) use ($actorUserId, $organizationId, $id, $existing): null {
            ($this->itemsFactory)($exec)->delete($id);

            ($this->auditFactory)($exec)->record($actorUserId, $organizationId, 'item.deleted', 'item', $id, ItemResponse::toArray($existing), null);

            return null;
        });
    }
}
