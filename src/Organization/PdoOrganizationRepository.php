<?php

declare(strict_types=1);

namespace NeneInvoice\Organization;

use Nene2\Database\DatabaseConstraintException;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Http\ClockInterface;

final readonly class PdoOrganizationRepository implements OrganizationRepositoryInterface
{
    private const COLUMNS = 'id, name, slug, external_id, custom_domain, plan, is_active, created_at, updated_at';

    public function __construct(
        private DatabaseQueryExecutorInterface $query,
        private ClockInterface $clock,
    ) {
    }

    public function findById(int $id): ?Organization
    {
        $row = $this->query->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM organizations WHERE id = ?',
            [$id],
        );

        return $row !== null ? $this->mapRow($row) : null;
    }

    public function findBySlug(string $slug): ?Organization
    {
        $row = $this->query->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM organizations WHERE slug = ?',
            [$slug],
        );

        return $row !== null ? $this->mapRow($row) : null;
    }

    public function findByCustomDomain(string $domain): ?Organization
    {
        $row = $this->query->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM organizations WHERE custom_domain = ?',
            [$domain],
        );

        return $row !== null ? $this->mapRow($row) : null;
    }

    public function findByExternalId(string $externalId): ?Organization
    {
        $row = $this->query->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM organizations WHERE external_id = ?',
            [$externalId],
        );

        return $row !== null ? $this->mapRow($row) : null;
    }

    /** @return list<Organization> */
    public function findAll(int $limit, int $offset): array
    {
        $rows = $this->query->fetchAll(
            'SELECT ' . self::COLUMNS . ' FROM organizations ORDER BY id ASC LIMIT ? OFFSET ?',
            [$limit, $offset],
        );

        return array_map(fn (array $row): Organization => $this->mapRow($row), $rows);
    }

    public function count(): int
    {
        $row = $this->query->fetchOne('SELECT COUNT(*) AS cnt FROM organizations', []);

        return $row !== null ? (int) $row['cnt'] : 0;
    }

    public function save(Organization $organization): int
    {
        $now = $this->clock->now()->format('Y-m-d H:i:s');

        try {
            $this->query->execute(
                'INSERT INTO organizations (name, slug, external_id, custom_domain, plan, is_active, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $organization->name,
                    $organization->slug,
                    $organization->externalId,
                    $organization->customDomain,
                    $organization->plan,
                    $organization->isActive ? 1 : 0,
                    $now,
                    $now,
                ],
            );
        } catch (DatabaseConstraintException $e) {
            // The only business-unique key on save is the slug (external_id is
            // optional and usually null), so a constraint violation here maps to
            // a slug conflict.
            throw new OrganizationSlugConflictException($organization->slug, $e);
        }

        return $this->query->lastInsertId();
    }

    public function update(Organization $organization): void
    {
        if ($organization->id === null) {
            throw new OrganizationNotFoundException(0);
        }

        $now = $this->clock->now()->format('Y-m-d H:i:s');

        $affected = $this->query->execute(
            'UPDATE organizations SET name = ?, slug = ?, external_id = ?, custom_domain = ?, plan = ?, is_active = ?, updated_at = ? WHERE id = ?',
            [
                $organization->name,
                $organization->slug,
                $organization->externalId,
                $organization->customDomain,
                $organization->plan,
                $organization->isActive ? 1 : 0,
                $now,
                $organization->id,
            ],
        );

        if ($affected === 0 && $this->findById($organization->id) === null) {
            throw new OrganizationNotFoundException($organization->id);
        }
    }

    public function delete(int $id): void
    {
        if ($this->findById($id) === null) {
            throw new OrganizationNotFoundException($id);
        }

        $this->query->execute('DELETE FROM organizations WHERE id = ?', [$id]);
    }

    /** @param array<string, mixed> $row */
    private function mapRow(array $row): Organization
    {
        return new Organization(
            name: (string) $row['name'],
            slug: (string) $row['slug'],
            plan: (string) $row['plan'],
            isActive: (bool) $row['is_active'],
            id: (int) $row['id'],
            externalId: isset($row['external_id']) && $row['external_id'] !== '' ? (string) $row['external_id'] : null,
            customDomain: isset($row['custom_domain']) && $row['custom_domain'] !== '' ? (string) $row['custom_domain'] : null,
            createdAt: (string) $row['created_at'],
            updatedAt: (string) $row['updated_at'],
        );
    }
}
