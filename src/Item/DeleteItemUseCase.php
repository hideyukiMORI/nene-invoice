<?php

declare(strict_types=1);

namespace NeneInvoice\Item;

use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Audit\AuditRecorderInterface;

final readonly class DeleteItemUseCase
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
     * Soft-deletes an item in the resolved organization. The repository scopes
     * the lookup/delete to the request org, so cross-organization targets
     * surface as not found.
     *
     * @throws ItemNotFoundException
     */
    public function execute(?int $actorUserId, int $id): void
    {
        $existing = $this->items->findById($id);

        if ($existing === null) {
            throw new ItemNotFoundException($id);
        }

        $this->items->delete($id);

        $this->audit->record($actorUserId, $this->orgId->get(), 'item.deleted', 'item', $id, ItemResponse::toArray($existing), null);
    }
}
