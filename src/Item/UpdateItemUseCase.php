<?php

declare(strict_types=1);

namespace NeneInvoice\Item;

use LogicException;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Audit\AuditRecorderInterface;

final readonly class UpdateItemUseCase implements UpdateItemUseCaseInterface
{
    /**
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private ItemRepositoryInterface $items,
        private AuditRecorderInterface $audit,
        private RequestScopedHolder $orgId,
    ) {
    }

    /**
     * Updates an item in the resolved organization. The repository scopes the
     * read/write to the request org, so an item from another organization (or
     * soft-deleted) surfaces as not found.
     *
     * @throws ItemNotFoundException
     */
    public function execute(?int $actorUserId, int $id, UpdateItemInput $input): Item
    {
        $existing = $this->items->findById($id);

        if ($existing === null) {
            throw new ItemNotFoundException($id);
        }

        $this->items->update(new Item(
            organizationId: $existing->organizationId,
            description: $input->description,
            defaultUnitPriceCents: $input->defaultUnitPriceCents,
            defaultTaxRateBps: $input->defaultTaxRateBps,
            isDeleted: false,
            id: $existing->id,
            createdAt: $existing->createdAt,
            updatedAt: $existing->updatedAt,
        ));

        $updated = $this->items->findById($id);

        if ($updated === null) {
            throw new LogicException('Item disappeared immediately after update.');
        }

        $this->audit->record($actorUserId, $this->orgId->get(), 'item.updated', 'item', $id, ItemResponse::toArray($existing), ItemResponse::toArray($updated));

        return $updated;
    }
}
