<?php

declare(strict_types=1);

namespace NeneInvoice\Item;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Http\RequestScopedHolder;

final readonly class PdoItemRepository implements ItemRepositoryInterface
{
    private const COLUMNS = 'id, organization_id, description, default_unit_price_cents, default_tax_rate_bps, is_deleted, created_at, updated_at';

    /**
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private DatabaseQueryExecutorInterface $query,
        private RequestScopedHolder $orgId,
    ) {
    }

    public function findById(int $id): ?Item
    {
        $row = $this->query->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM items WHERE id = ? AND organization_id = ? AND is_deleted = 0',
            [$id, $this->orgId->get()],
        );

        return $row !== null ? $this->mapRow($row) : null;
    }

    /** @return list<Item> */
    public function findAll(int $limit, int $offset): array
    {
        $rows = $this->query->fetchAll(
            'SELECT ' . self::COLUMNS . ' FROM items WHERE organization_id = ? AND is_deleted = 0 ORDER BY id ASC LIMIT ? OFFSET ?',
            [$this->orgId->get(), $limit, $offset],
        );

        return array_map(fn (array $row): Item => $this->mapRow($row), $rows);
    }

    public function count(): int
    {
        $row = $this->query->fetchOne(
            'SELECT COUNT(*) AS cnt FROM items WHERE organization_id = ? AND is_deleted = 0',
            [$this->orgId->get()],
        );

        return $row !== null ? (int) $row['cnt'] : 0;
    }

    /**
     * Admin list: searched + sorted.
     *
     * @return list<Item>
     */
    public function findForAdminList(ItemListFilter $filter, ItemSort $sort, int $limit, int $offset): array
    {
        [$where, $params] = $this->buildAdminWhere($filter);

        $rows = $this->query->fetchAll(
            'SELECT ' . self::COLUMNS . ' FROM items WHERE ' . $where
                . ' ORDER BY ' . self::orderByClause($sort) . ' LIMIT ? OFFSET ?',
            [...$params, $limit, $offset],
        );

        return array_map(fn (array $row): Item => $this->mapRow($row), $rows);
    }

    public function countForAdminList(ItemListFilter $filter): int
    {
        [$where, $params] = $this->buildAdminWhere($filter);

        $row = $this->query->fetchOne('SELECT COUNT(*) AS cnt FROM items WHERE ' . $where, $params);

        return $row !== null ? (int) $row['cnt'] : 0;
    }

    /**
     * @return array{0: string, 1: list<int|string>}
     */
    private function buildAdminWhere(ItemListFilter $filter): array
    {
        $clauses = ['organization_id = ?', 'is_deleted = 0'];
        /** @var list<int|string> $params */
        $params = [$this->orgId->get()];

        if ($filter->search !== null) {
            $clauses[] = "description LIKE ? ESCAPE '!'";
            $params[] = '%' . self::escapeLike($filter->search) . '%';
        }

        return [implode(' AND ', $clauses), $params];
    }

    /** Maps a whitelisted sort field to a SQL ORDER BY, with `id` as tiebreak. */
    private static function orderByClause(ItemSort $sort): string
    {
        $columns = [
            'description' => 'description',
            'unit_price'  => 'default_unit_price_cents',
            'tax_rate'    => 'default_tax_rate_bps',
        ];

        $direction = $sort->descending ? 'DESC' : 'ASC';

        if ($sort->field === null || !isset($columns[$sort->field])) {
            return 'description ' . $direction . ', id ASC';
        }

        return $columns[$sort->field] . ' ' . $direction . ', id ASC';
    }

    /** Escapes LIKE wildcards (ESCAPE '!') so user input is matched literally. */
    private static function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }

    public function save(Item $item): int
    {
        $now = date('Y-m-d H:i:s');

        // The organization is forced from the request-scoped holder, never from
        // the entity — a write always lands in the caller's resolved org.
        $this->query->execute(
            'INSERT INTO items (organization_id, description, default_unit_price_cents, default_tax_rate_bps, is_deleted, created_at, updated_at)
             VALUES (?, ?, ?, ?, 0, ?, ?)',
            [
                $this->orgId->get(),
                $item->description,
                $item->defaultUnitPriceCents,
                $item->defaultTaxRateBps,
                $now,
                $now,
            ],
        );

        return $this->query->lastInsertId();
    }

    public function update(Item $item): void
    {
        if ($item->id === null) {
            throw new ItemNotFoundException(0);
        }

        $now = date('Y-m-d H:i:s');

        $affected = $this->query->execute(
            'UPDATE items SET description = ?, default_unit_price_cents = ?, default_tax_rate_bps = ?, updated_at = ? WHERE id = ? AND organization_id = ? AND is_deleted = 0',
            [
                $item->description,
                $item->defaultUnitPriceCents,
                $item->defaultTaxRateBps,
                $now,
                $item->id,
                $this->orgId->get(),
            ],
        );

        if ($affected === 0 && $this->findById($item->id) === null) {
            throw new ItemNotFoundException($item->id);
        }
    }

    public function delete(int $id): void
    {
        if ($this->findById($id) === null) {
            throw new ItemNotFoundException($id);
        }

        $this->query->execute(
            'UPDATE items SET is_deleted = 1, deleted_at = ? WHERE id = ? AND organization_id = ?',
            [date('Y-m-d H:i:s'), $id, $this->orgId->get()],
        );
    }

    /** @param array<string, mixed> $row */
    private function mapRow(array $row): Item
    {
        return new Item(
            organizationId: (int) $row['organization_id'],
            description: (string) $row['description'],
            defaultUnitPriceCents: (int) $row['default_unit_price_cents'],
            defaultTaxRateBps: (int) $row['default_tax_rate_bps'],
            isDeleted: (bool) $row['is_deleted'],
            id: (int) $row['id'],
            createdAt: (string) $row['created_at'],
            updatedAt: (string) $row['updated_at'],
        );
    }
}
