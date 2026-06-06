<?php

declare(strict_types=1);

namespace NeneInvoice\Item;

use LogicException;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Audit\AuditRecorderInterface;

final readonly class CreateItemUseCase implements CreateItemUseCaseInterface
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
     * Creates an item in the resolved organization (the repository forces the
     * org from the request-scoped holder, never from request input).
     */
    public function execute(?int $actorUserId, CreateItemInput $input): Item
    {
        $organizationId = $this->orgId->get();

        $id = $this->items->save(new Item(
            organizationId: $organizationId,
            description: $input->description,
            defaultUnitPriceCents: $input->defaultUnitPriceCents,
            defaultTaxRateBps: $input->defaultTaxRateBps,
        ));

        $created = $this->items->findById($id);

        if ($created === null) {
            throw new LogicException('Item disappeared immediately after creation.');
        }

        $this->audit->record($actorUserId, $organizationId, 'item.created', 'item', $id, null, ItemResponse::toArray($created));

        return $created;
    }
}
