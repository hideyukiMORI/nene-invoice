<?php

declare(strict_types=1);

namespace NeneInvoice\Company;

/**
 * Persistence for the per-organization issuer profile. There is at most one row
 * per organization; {@see save()} upserts.
 */
interface CompanySettingsRepositoryInterface
{
    public function findByOrganization(int $organizationId): ?CompanySettings;

    public function save(CompanySettings $settings): void;
}
