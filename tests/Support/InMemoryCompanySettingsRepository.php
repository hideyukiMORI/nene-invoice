<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Support;

use NeneInvoice\Company\CompanySettings;
use NeneInvoice\Company\CompanySettingsRepositoryInterface;

/**
 * In-memory fake for use-case tests (no database).
 */
final class InMemoryCompanySettingsRepository implements CompanySettingsRepositoryInterface
{
    /** @var array<int, CompanySettings> */
    private array $byOrganization = [];

    public function findByOrganization(int $organizationId): ?CompanySettings
    {
        return $this->byOrganization[$organizationId] ?? null;
    }

    public function save(CompanySettings $settings): void
    {
        $this->byOrganization[$settings->organizationId] = $settings;
    }
}
