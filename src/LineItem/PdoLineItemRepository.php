<?php

declare(strict_types=1);

namespace NeneInvoice\LineItem;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Http\ClockInterface;
use Nene2\Http\RequestScopedHolder;

final readonly class PdoLineItemRepository implements LineItemRepositoryInterface
{
    private const COLUMNS = 'id, parent_type, parent_id, description, quantity, unit_price_cents, tax_rate_bps, sort_order, created_at, updated_at';

    /**
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private DatabaseQueryExecutorInterface $query,
        private RequestScopedHolder $orgId,
        private ClockInterface $clock,
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

        $now = $this->clock->now()->format('Y-m-d H:i:s');

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

    /**
     * @return list<array{description: string, unit_price_cents: int, tax_rate_bps: int, created_at: string}>
     */
    public function recentForOrganization(int $limit): array
    {
        $orgId = $this->orgId->get();

        // Pull the org's line items from both document types in one pass. The
        // org boundary lives on the parent (line_items has no organization_id),
        // so we join through invoices/quotes and exclude soft-deleted parents.
        // Aggregation (group by description, pick defaults) happens in PHP to
        // stay dialect-agnostic — same approach as the dashboard series.
        $rows = $this->query->fetchAll(
            'SELECT li.description AS description, li.unit_price_cents AS unit_price_cents,
                    li.tax_rate_bps AS tax_rate_bps, li.created_at AS created_at
               FROM line_items li
               JOIN invoices i ON li.parent_type = ? AND li.parent_id = i.id
              WHERE i.organization_id = ? AND i.is_deleted = FALSE
             UNION ALL
             SELECT li.description AS description, li.unit_price_cents AS unit_price_cents,
                    li.tax_rate_bps AS tax_rate_bps, li.created_at AS created_at
               FROM line_items li
               JOIN quotes q ON li.parent_type = ? AND li.parent_id = q.id
              WHERE q.organization_id = ? AND q.is_deleted = FALSE
             ORDER BY created_at DESC, description ASC
             LIMIT ?',
            [
                LineItemParent::Invoice->value,
                $orgId,
                LineItemParent::Quote->value,
                $orgId,
                $limit,
            ],
        );

        return array_map(
            static fn (array $row): array => [
                'description'      => (string) $row['description'],
                'unit_price_cents' => (int) $row['unit_price_cents'],
                'tax_rate_bps'     => (int) $row['tax_rate_bps'],
                'created_at'       => (string) $row['created_at'],
            ],
            $rows,
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
