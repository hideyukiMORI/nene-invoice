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

final readonly class CreateItemUseCase implements CreateItemUseCaseInterface
{
    /**
     * @param Closure(DatabaseQueryExecutorInterface): ItemRepositoryInterface $itemsFactory
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private DatabaseTransactionManagerInterface $tx,
        private Closure $itemsFactory,
        private AuditRecorderFactoryInterface $auditFactory,
        private RequestScopedHolder $orgId,
    ) {
    }

    /**
     * Creates an item in the resolved organization (the repository forces the
     * org from the request-scoped holder, never from request input). The write
     * and its audit record commit atomically (Issue #352).
     */
    public function execute(?int $actorUserId, CreateItemInput $input): Item
    {
        $organizationId = $this->orgId->get();

        return $this->tx->transactional(function (DatabaseQueryExecutorInterface $exec) use ($actorUserId, $organizationId, $input): Item {
            $items = ($this->itemsFactory)($exec);

            $id = $items->save(new Item(
                organizationId: $organizationId,
                description: $input->description,
                defaultUnitPriceCents: $input->defaultUnitPriceCents,
                defaultTaxRateBps: $input->defaultTaxRateBps,
            ));

            $created = $items->findById($id);

            if ($created === null) {
                throw new LogicException('Item disappeared immediately after creation.');
            }

            $this->auditFactory->forExecutor($exec)->record(new AuditEvent(
                action: 'item.created',
                entityType: 'item',
                entityId: $id,
                actorId: $actorUserId,
                organizationId: $organizationId,
                before: null,
                after: ItemResponse::toArray($created),
            ));

            return $created;
        });
    }
}
