<?php

declare(strict_types=1);

namespace NeneInvoice\Item;

final readonly class GetItemByIdUseCase implements GetItemByIdUseCaseInterface
{
    public function __construct(
        private ItemRepositoryInterface $items,
    ) {
    }

    /**
     * Fetches an item in the current organization. The repository scopes the
     * read to the request-scoped org, so an item from another organization (or
     * a missing/soft-deleted id) surfaces as not found — no cross-tenant leak.
     *
     * @throws ItemNotFoundException
     */
    public function execute(int $id): Item
    {
        $item = $this->items->findById($id);

        if ($item === null) {
            throw new ItemNotFoundException($id);
        }

        return $item;
    }
}
