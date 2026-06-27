<?php

declare(strict_types=1);

namespace NeneInvoice\Organization;

interface OrganizationRepositoryInterface
{
    public function findById(int $id): ?Organization;

    public function findBySlug(string $slug): ?Organization;

    public function findByCustomDomain(string $domain): ?Organization;

    /**
     * Resolve a tenant by its federation link (`organizations.external_id`,
     * value `org_external_id`; ADR 0016 §2). Suite-mode SSO maps a suite
     * assertion's `org_external_id` to the local org with this. Returns null in
     * standalone installs (the column is null there).
     */
    public function findByExternalId(string $externalId): ?Organization;

    /** @return list<Organization> */
    public function findAll(int $limit, int $offset): array;

    public function count(): int;

    /** @throws OrganizationSlugConflictException */
    public function save(Organization $organization): int;

    /** @throws OrganizationNotFoundException */
    public function update(Organization $organization): void;

    /** @throws OrganizationNotFoundException */
    public function delete(int $id): void;
}
