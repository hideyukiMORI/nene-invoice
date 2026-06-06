<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Support;

use NeneInvoice\LineItem\LineItem;
use NeneInvoice\LineItem\LineItemParent;
use NeneInvoice\LineItem\LineItemRepositoryInterface;

/**
 * In-memory fake for use-case tests (no database).
 */
final class InMemoryLineItemRepository implements LineItemRepositoryInterface
{
    /** @var array<string, list<LineItem>> */
    private array $byParent = [];
    private int $nextId = 1;

    /** @return list<LineItem> */
    public function findByParent(LineItemParent $parentType, int $parentId): array
    {
        $lines = $this->byParent[$this->key($parentType, $parentId)] ?? [];

        usort($lines, static fn (LineItem $a, LineItem $b): int => $a->sortOrder <=> $b->sortOrder);

        return $lines;
    }

    /** @param list<LineItem> $lines */
    public function replaceForParent(LineItemParent $parentType, int $parentId, array $lines): void
    {
        $stored = [];

        foreach ($lines as $line) {
            $stored[] = new LineItem(
                parentType: $parentType,
                parentId: $parentId,
                description: $line->description,
                quantity: $line->quantity,
                unitPriceCents: $line->unitPriceCents,
                taxRateBps: $line->taxRateBps,
                sortOrder: $line->sortOrder,
                id: $this->nextId++,
                createdAt: '2026-05-29 00:00:00',
                updatedAt: '2026-05-29 00:00:00',
            );
        }

        $this->byParent[$this->key($parentType, $parentId)] = $stored;
    }

    public function deleteForParent(LineItemParent $parentType, int $parentId): void
    {
        unset($this->byParent[$this->key($parentType, $parentId)]);
    }

    /**
     * Returns every stored line as a suggestion row, newest-first by created_at.
     * Org/soft-delete scoping is a SQL concern; this fake holds only one org's
     * line items, so use-case tests drive aggregation through what they seed.
     *
     * @return list<array{description: string, unit_price_cents: int, tax_rate_bps: int, created_at: string}>
     */
    public function recentForOrganization(int $limit): array
    {
        $rows = [];

        foreach ($this->byParent as $lines) {
            foreach ($lines as $line) {
                $rows[] = [
                    'description'      => $line->description,
                    'unit_price_cents' => $line->unitPriceCents,
                    'tax_rate_bps'     => $line->taxRateBps,
                    'created_at'       => $line->createdAt ?? '',
                ];
            }
        }

        usort($rows, static fn (array $a, array $b): int => $b['created_at'] <=> $a['created_at']);

        return array_slice($rows, 0, $limit);
    }

    private function key(LineItemParent $parentType, int $parentId): string
    {
        return $parentType->value . ':' . $parentId;
    }
}
