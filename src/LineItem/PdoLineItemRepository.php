<?php

declare(strict_types=1);

namespace NeneInvoice\LineItem;

use Nene2\Database\DatabaseQueryExecutorInterface;

final readonly class PdoLineItemRepository implements LineItemRepositoryInterface
{
    private const COLUMNS = 'id, parent_type, parent_id, description, quantity, unit_price_cents, tax_rate_bps, sort_order, created_at, updated_at';

    public function __construct(
        private DatabaseQueryExecutorInterface $query,
    ) {
    }

    /** @return list<LineItem> */
    public function findByParent(LineItemParent $parentType, int $parentId): array
    {
        $rows = $this->query->fetchAll(
            'SELECT ' . self::COLUMNS . ' FROM line_items WHERE parent_type = ? AND parent_id = ? ORDER BY sort_order ASC, id ASC',
            [$parentType->value, $parentId],
        );

        return array_map(fn (array $row): LineItem => $this->mapRow($row), $rows);
    }

    /** @param list<LineItem> $lines */
    public function replaceForParent(LineItemParent $parentType, int $parentId, array $lines): void
    {
        $this->deleteForParent($parentType, $parentId);

        $now = date('Y-m-d H:i:s');

        foreach ($lines as $line) {
            $this->query->execute(
                'INSERT INTO line_items (parent_type, parent_id, description, quantity, unit_price_cents, tax_rate_bps, sort_order, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $parentType->value,
                    $parentId,
                    $line->description,
                    $line->quantity,
                    $line->unitPriceCents,
                    $line->taxRateBps,
                    $line->sortOrder,
                    $now,
                    $now,
                ],
            );
        }
    }

    public function deleteForParent(LineItemParent $parentType, int $parentId): void
    {
        $this->query->execute(
            'DELETE FROM line_items WHERE parent_type = ? AND parent_id = ?',
            [$parentType->value, $parentId],
        );
    }

    /** @param array<string, mixed> $row */
    private function mapRow(array $row): LineItem
    {
        return new LineItem(
            parentType: LineItemParent::from((string) $row['parent_type']),
            parentId: (int) $row['parent_id'],
            description: (string) $row['description'],
            quantity: (int) $row['quantity'],
            unitPriceCents: (int) $row['unit_price_cents'],
            taxRateBps: (int) $row['tax_rate_bps'],
            sortOrder: (int) $row['sort_order'],
            id: (int) $row['id'],
            createdAt: (string) $row['created_at'],
            updatedAt: (string) $row['updated_at'],
        );
    }
}
