<?php

declare(strict_types=1);

namespace NeneInvoice\Item;

use Closure;
use LogicException;
use Nene2\Audit\AuditEvent;
use Nene2\Audit\AuditRecorderFactoryInterface;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\RequestScopedHolder;

final readonly class UpdateItemUseCase implements UpdateItemUseCaseInterface
{
    /**
     * @param Closure(DatabaseQueryExecutorInterface): ItemRepositoryInterface $itemsFactory
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private ItemRepositoryInterface $items,
        private DatabaseTransactionManagerInterface $tx,
        private Closure $itemsFactory,
        private AuditRecorderFactoryInterface $auditFactory,
        private RequestScopedHolder $orgId,
    ) {
    }

    /**
     * Updates an item in the resolved organization. The repository scopes the
     * read/write to the request org, so an item from another organization (or
     * soft-deleted) surfaces as not found. The write and its audit record commit
     * atomically (Issue #352).
     *
     * @throws ItemNotFoundException
     */
    public function execute(?int $actorUserId, int $id, UpdateItemInput $input): Item
    {
        $existing = $this->items->findById($id);

        if ($existing === null) {
            throw new ItemNotFoundException($id);
        }

        $organizationId = $this->orgId->get();

        return $this->tx->transactional(function (DatabaseQueryExecutorInterface $exec) use ($actorUserId, $organizationId, $id, $input, $existing): Item {
            $items = ($this->itemsFactory)($exec);

            $items->update(new Item(
                organizationId: $existing->organizationId,
                description: $input->description,
                defaultUnitPriceCents: $input->defaultUnitPriceCents,
                defaultTaxRateBps: $input->defaultTaxRateBps,
                isDeleted: false,
                id: $existing->id,
                createdAt: $existing->createdAt,
                updatedAt: $existing->updatedAt,
            ));

            $updated = $items->findById($id);

            if ($updated === null) {
                throw new LogicException('Item disappeared immediately after update.');
            }

            $this->auditFactory->forExecutor($exec)->record(new AuditEvent(
                action: 'item.updated',
                entityType: 'item',
                entityId: $id,
                actorId: $actorUserId,
                organizationId: $organizationId,
                before: ItemResponse::toArray($existing),
                after: ItemResponse::toArray($updated),
            ));

            return $updated;
        });
    }
}
