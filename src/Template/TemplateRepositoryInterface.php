<?php

declare(strict_types=1);

namespace NeneInvoice\Template;

/**
 * Persistence for template headers. Every query is scoped to the organization
 * held in the request-scoped org holder (ADR 0006). Reads exclude soft-deleted
 * rows; `delete` is a soft delete. Line presets are handled separately via the
 * shared line-item repository (parent_type = 'template').
 */
interface TemplateRepositoryInterface
{
    public function findById(int $id): ?Template;

    /** @return list<Template> newest first */
    public function findAll(int $limit, int $offset): array;

    public function count(): int;

    public function save(Template $template): int;

    /** @throws TemplateNotFoundException */
    public function update(Template $template): void;

    /** @throws TemplateNotFoundException */
    public function delete(int $id): void;
}
