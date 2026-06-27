<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Support;

use NeneInvoice\Organization\Organization;
use NeneInvoice\Organization\OrganizationNotFoundException;
use NeneInvoice\Organization\OrganizationRepositoryInterface;
use NeneInvoice\Organization\OrganizationSlugConflictException;

/**
 * In-memory fake for use-case tests (no database).
 */
final class InMemoryOrganizationRepository implements OrganizationRepositoryInterface
{
    /** @var array<int, Organization> */
    private array $byId = [];
    private int $nextId = 1;

    public function findById(int $id): ?Organization
    {
        return $this->byId[$id] ?? null;
    }

    public function findBySlug(string $slug): ?Organization
    {
        foreach ($this->byId as $organization) {
            if ($organization->slug === $slug) {
                return $organization;
            }
        }

        return null;
    }

    public function findByCustomDomain(string $domain): ?Organization
    {
        foreach ($this->byId as $organization) {
            if ($organization->customDomain === $domain) {
                return $organization;
            }
        }

        return null;
    }

    public function findByExternalId(string $externalId): ?Organization
    {
        foreach ($this->byId as $organization) {
            if ($organization->externalId === $externalId) {
                return $organization;
            }
        }

        return null;
    }

    /** @return list<Organization> */
    public function findAll(int $limit, int $offset): array
    {
        return array_slice(array_values($this->byId), $offset, $limit);
    }

    public function count(): int
    {
        return count($this->byId);
    }

    public function save(Organization $organization): int
    {
        if ($this->findBySlug($organization->slug) !== null) {
            throw new OrganizationSlugConflictException($organization->slug);
        }

        $id = $this->nextId++;
        $now = '2026-05-29 00:00:00';

        $this->byId[$id] = new Organization(
            name: $organization->name,
            slug: $organization->slug,
            plan: $organization->plan,
            isActive: $organization->isActive,
            id: $id,
            externalId: $organization->externalId,
            customDomain: $organization->customDomain,
            createdAt: $now,
            updatedAt: $now,
        );

        return $id;
    }

    public function update(Organization $organization): void
    {
        if ($organization->id === null || !isset($this->byId[$organization->id])) {
            throw new OrganizationNotFoundException($organization->id ?? 0);
        }

        $this->byId[$organization->id] = $organization;
    }

    public function delete(int $id): void
    {
        if (!isset($this->byId[$id])) {
            throw new OrganizationNotFoundException($id);
        }

        unset($this->byId[$id]);
    }
}
