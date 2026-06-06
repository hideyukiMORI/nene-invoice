<?php

declare(strict_types=1);

namespace NeneInvoice\Template;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Http\RequestScopedHolder;

final readonly class PdoTemplateRepository implements TemplateRepositoryInterface
{
    private const COLUMNS = 'id, organization_id, name, notes, is_deleted, created_at, updated_at';

    /**
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private DatabaseQueryExecutorInterface $query,
        private RequestScopedHolder $orgId,
    ) {
    }

    public function findById(int $id): ?Template
    {
        $row = $this->query->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM templates WHERE id = ? AND organization_id = ? AND is_deleted = 0',
            [$id, $this->orgId->get()],
        );

        return $row !== null ? $this->mapRow($row) : null;
    }

    /** @return list<Template> */
    public function findAll(int $limit, int $offset): array
    {
        $rows = $this->query->fetchAll(
            'SELECT ' . self::COLUMNS . ' FROM templates WHERE organization_id = ? AND is_deleted = 0 ORDER BY name ASC, id ASC LIMIT ? OFFSET ?',
            [$this->orgId->get(), $limit, $offset],
        );

        return array_map(fn (array $row): Template => $this->mapRow($row), $rows);
    }

    public function count(): int
    {
        $row = $this->query->fetchOne(
            'SELECT COUNT(*) AS cnt FROM templates WHERE organization_id = ? AND is_deleted = 0',
            [$this->orgId->get()],
        );

        return $row !== null ? (int) $row['cnt'] : 0;
    }

    public function save(Template $template): int
    {
        $now = date('Y-m-d H:i:s');

        // The organization is forced from the request-scoped holder, never from
        // the entity — a write always lands in the caller's resolved org.
        $this->query->execute(
            'INSERT INTO templates (organization_id, name, notes, is_deleted, created_at, updated_at)
             VALUES (?, ?, ?, 0, ?, ?)',
            [$this->orgId->get(), $template->name, $template->notes, $now, $now],
        );

        return $this->query->lastInsertId();
    }

    public function update(Template $template): void
    {
        if ($template->id === null) {
            throw new TemplateNotFoundException(0);
        }

        $now = date('Y-m-d H:i:s');

        $affected = $this->query->execute(
            'UPDATE templates SET name = ?, notes = ?, updated_at = ? WHERE id = ? AND organization_id = ? AND is_deleted = 0',
            [$template->name, $template->notes, $now, $template->id, $this->orgId->get()],
        );

        if ($affected === 0 && $this->findById($template->id) === null) {
            throw new TemplateNotFoundException($template->id);
        }
    }

    public function delete(int $id): void
    {
        if ($this->findById($id) === null) {
            throw new TemplateNotFoundException($id);
        }

        $this->query->execute(
            'UPDATE templates SET is_deleted = 1, deleted_at = ? WHERE id = ? AND organization_id = ?',
            [date('Y-m-d H:i:s'), $id, $this->orgId->get()],
        );
    }

    /** @param array<string, mixed> $row */
    private function mapRow(array $row): Template
    {
        return new Template(
            organizationId: (int) $row['organization_id'],
            name: (string) $row['name'],
            notes: isset($row['notes']) && $row['notes'] !== '' ? (string) $row['notes'] : null,
            isDeleted: (bool) $row['is_deleted'],
            id: (int) $row['id'],
            createdAt: (string) $row['created_at'],
            updatedAt: (string) $row['updated_at'],
        );
    }
}
