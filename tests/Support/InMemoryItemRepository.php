<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Support;

use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Item\Item;
use NeneInvoice\Item\ItemListFilter;
use NeneInvoice\Item\ItemNotFoundException;
use NeneInvoice\Item\ItemRepositoryInterface;
use NeneInvoice\Item\ItemSort;

/**
 * In-memory fake for use-case tests (no database). Reads are scoped to the
 * request-scoped org holder, mirroring {@see \NeneInvoice\Item\PdoItemRepository}.
 * `save` keeps the entity's org so tests can seed cross-tenant fixtures; reads
 * then prove the holder-based isolation. Soft-deleted items are excluded.
 *
 * The holder defaults to organization 1 so existing single-org tests keep
 * working without wiring; pass a preset holder to test other organizations.
 */
final class InMemoryItemRepository implements ItemRepositoryInterface
{
    /** @var array<int, Item> */
    private array $byId = [];
    private int $nextId = 1;

    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $orgId;

    /** @param RequestScopedHolder<int>|null $orgId */
    public function __construct(?RequestScopedHolder $orgId = null)
    {
        if ($orgId === null) {
            $orgId = new RequestScopedHolder();
            $orgId->set(1);
        }

        $this->orgId = $orgId;
    }

    public function findById(int $id): ?Item
    {
        $item = $this->byId[$id] ?? null;

        return $item !== null && !$item->isDeleted && $item->organizationId === $this->orgId->get()
            ? $item
            : null;
    }

    /** @return list<Item> */
    public function findAll(int $limit, int $offset): array
    {
        $matches = array_values(array_filter(
            $this->byId,
            fn (Item $i): bool => $i->organizationId === $this->orgId->get() && !$i->isDeleted,
        ));

        return array_slice($matches, $offset, $limit);
    }

    public function count(): int
    {
        return count(array_filter(
            $this->byId,
            fn (Item $i): bool => $i->organizationId === $this->orgId->get() && !$i->isDeleted,
        ));
    }

    /** @return list<Item> */
    public function findForAdminList(ItemListFilter $filter, ItemSort $sort, int $limit, int $offset): array
    {
        $matches = $this->adminFiltered($filter);

        usort($matches, static function (Item $a, Item $b) use ($sort): int {
            $cmp = match ($sort->field) {
                'unit_price' => $a->defaultUnitPriceCents <=> $b->defaultUnitPriceCents,
                'tax_rate'   => $a->defaultTaxRateBps <=> $b->defaultTaxRateBps,
                default      => strcmp($a->description, $b->description),
            };

            return $sort->descending ? -$cmp : $cmp;
        });

        return array_slice($matches, $offset, $limit);
    }

    public function countForAdminList(ItemListFilter $filter): int
    {
        return count($this->adminFiltered($filter));
    }

    /** @return list<Item> */
    private function adminFiltered(ItemListFilter $filter): array
    {
        $orgId  = $this->orgId->get();
        $search = $filter->search;

        return array_values(array_filter($this->byId, static function (Item $i) use ($orgId, $search): bool {
            if ($i->organizationId !== $orgId || $i->isDeleted) {
                return false;
            }

            return $search === null || stripos($i->description, $search) !== false;
        }));
    }

    public function save(Item $item): int
    {
        $id = $this->nextId++;
        $this->byId[$id] = new Item(
            organizationId: $item->organizationId,
            description: $item->description,
            defaultUnitPriceCents: $item->defaultUnitPriceCents,
            defaultTaxRateBps: $item->defaultTaxRateBps,
            isDeleted: false,
            id: $id,
            createdAt: '2026-06-06 00:00:00',
            updatedAt: '2026-06-06 00:00:00',
        );

        return $id;
    }

    public function update(Item $item): void
    {
        if ($item->id === null || $this->findById($item->id) === null) {
            throw new ItemNotFoundException($item->id ?? 0);
        }

        $this->byId[$item->id] = $item;
    }

    public function delete(int $id): void
    {
        $existing = $this->findById($id);

        if ($existing === null) {
            throw new ItemNotFoundException($id);
        }

        $this->byId[$id] = new Item(
            organizationId: $existing->organizationId,
            description: $existing->description,
            defaultUnitPriceCents: $existing->defaultUnitPriceCents,
            defaultTaxRateBps: $existing->defaultTaxRateBps,
            isDeleted: true,
            id: $existing->id,
            createdAt: $existing->createdAt,
            updatedAt: $existing->updatedAt,
        );
    }
}
