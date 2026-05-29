<?php

declare(strict_types=1);

namespace NeneInvoice\LineItem;

/**
 * Persistence for line items, addressed by their polymorphic parent.
 */
interface LineItemRepositoryInterface
{
    /** @return list<LineItem> ordered by sort_order */
    public function findByParent(LineItemParent $parentType, int $parentId): array;

    /**
     * Replaces all line items for a parent with the given set (delete + insert).
     *
     * @param list<LineItem> $lines
     */
    public function replaceForParent(LineItemParent $parentType, int $parentId, array $lines): void;

    public function deleteForParent(LineItemParent $parentType, int $parentId): void;
}
