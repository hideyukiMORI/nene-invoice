<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Support;

use Nene2\Http\RequestScopedHolder;
use NeneInvoice\RecurringInvoice\RecurringInvoice;
use NeneInvoice\RecurringInvoice\RecurringInvoiceNotFoundException;
use NeneInvoice\RecurringInvoice\RecurringInvoiceRepositoryInterface;

/**
 * In-memory fake for use-case tests (no database). Reads are scoped to the
 * request-scoped org holder, mirroring {@see \NeneInvoice\RecurringInvoice\PdoRecurringInvoiceRepository}.
 */
final class InMemoryRecurringInvoiceRepository implements RecurringInvoiceRepositoryInterface
{
    /** @var array<int, RecurringInvoice> */
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

    public function save(RecurringInvoice $schedule): int
    {
        $id = $this->nextId++;
        $this->byId[$id] = $this->withId($schedule, $id, $schedule->isDeleted);

        return $id;
    }

    public function findById(int $id): ?RecurringInvoice
    {
        $schedule = $this->byId[$id] ?? null;

        return $schedule !== null && !$schedule->isDeleted && $schedule->organizationId === $this->orgId->get()
            ? $schedule
            : null;
    }

    /** @return list<RecurringInvoice> */
    public function findByOrganization(int $limit, int $offset): array
    {
        return array_slice($this->mine(), $offset, $limit);
    }

    public function countByOrganization(): int
    {
        return count($this->mine());
    }

    /** @return list<RecurringInvoice> */
    public function findDue(string $onDate): array
    {
        $due = array_values(array_filter(
            $this->mine(),
            static fn (RecurringInvoice $s): bool => $s->isActive && $s->nextRunOn <= $onDate,
        ));

        usort($due, static fn (RecurringInvoice $a, RecurringInvoice $b): int => [$a->nextRunOn, $a->id ?? 0] <=> [$b->nextRunOn, $b->id ?? 0]);

        return $due;
    }

    public function update(RecurringInvoice $schedule): void
    {
        if ($schedule->id === null || $this->findById($schedule->id) === null) {
            throw new RecurringInvoiceNotFoundException($schedule->id ?? 0);
        }

        $this->byId[$schedule->id] = $this->withId($schedule, $schedule->id, false);
    }

    public function delete(int $id): void
    {
        $existing = $this->findById($id);

        if ($existing === null) {
            throw new RecurringInvoiceNotFoundException($id);
        }

        $this->byId[$id] = $this->withId($existing, $id, true);
    }

    /** @return list<RecurringInvoice> newest first, org-scoped, excluding deleted */
    private function mine(): array
    {
        $mine = array_values(array_filter(
            $this->byId,
            fn (RecurringInvoice $s): bool => $s->organizationId === $this->orgId->get() && !$s->isDeleted,
        ));

        usort($mine, static fn (RecurringInvoice $a, RecurringInvoice $b): int => ($b->id ?? 0) <=> ($a->id ?? 0));

        return $mine;
    }

    private function withId(RecurringInvoice $s, int $id, bool $isDeleted): RecurringInvoice
    {
        return new RecurringInvoice(
            organizationId: $s->organizationId,
            clientId: $s->clientId,
            name: $s->name,
            frequency: $s->frequency,
            subtotalCents: $s->subtotalCents,
            taxCents: $s->taxCents,
            totalCents: $s->totalCents,
            nextRunOn: $s->nextRunOn,
            lastRunOn: $s->lastRunOn,
            isActive: $s->isActive,
            notes: $s->notes,
            isDeleted: $isDeleted,
            id: $id,
            createdAt: $s->createdAt ?? '2026-06-06 00:00:00',
            updatedAt: '2026-06-06 00:00:00',
        );
    }
}
