<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Support;

use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Template\Template;
use NeneInvoice\Template\TemplateNotFoundException;
use NeneInvoice\Template\TemplateRepositoryInterface;

/**
 * In-memory fake for use-case tests (no database). Reads are scoped to the
 * request-scoped org holder, mirroring {@see \NeneInvoice\Template\PdoTemplateRepository}.
 * Soft-deleted templates are excluded. Holder defaults to org 1.
 */
final class InMemoryTemplateRepository implements TemplateRepositoryInterface
{
    /** @var array<int, Template> */
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

    public function findById(int $id): ?Template
    {
        $t = $this->byId[$id] ?? null;

        return $t !== null && !$t->isDeleted && $t->organizationId === $this->orgId->get() ? $t : null;
    }

    /** @return list<Template> */
    public function findAll(int $limit, int $offset): array
    {
        $matches = array_values(array_filter(
            $this->byId,
            fn (Template $t): bool => $t->organizationId === $this->orgId->get() && !$t->isDeleted,
        ));

        return array_slice($matches, $offset, $limit);
    }

    public function count(): int
    {
        return count(array_filter(
            $this->byId,
            fn (Template $t): bool => $t->organizationId === $this->orgId->get() && !$t->isDeleted,
        ));
    }

    public function save(Template $template): int
    {
        $id = $this->nextId++;
        $this->byId[$id] = new Template(
            organizationId: $template->organizationId,
            name: $template->name,
            notes: $template->notes,
            isDeleted: false,
            id: $id,
            createdAt: '2026-06-06 00:00:00',
            updatedAt: '2026-06-06 00:00:00',
        );

        return $id;
    }

    public function update(Template $template): void
    {
        if ($template->id === null || $this->findById($template->id) === null) {
            throw new TemplateNotFoundException($template->id ?? 0);
        }

        $this->byId[$template->id] = $template;
    }

    public function delete(int $id): void
    {
        $existing = $this->findById($id);

        if ($existing === null) {
            throw new TemplateNotFoundException($id);
        }

        $this->byId[$id] = new Template(
            organizationId: $existing->organizationId,
            name: $existing->name,
            notes: $existing->notes,
            isDeleted: true,
            id: $existing->id,
            createdAt: $existing->createdAt,
            updatedAt: $existing->updatedAt,
        );
    }
}
