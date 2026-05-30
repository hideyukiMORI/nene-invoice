<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Support;

use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Company\CompanySettings;
use NeneInvoice\Company\CompanySettingsRepositoryInterface;

/**
 * In-memory fake for use-case tests (no database). Reads are scoped to the
 * request-scoped org holder, mirroring {@see \NeneInvoice\Company\PdoCompanySettingsRepository}.
 * The holder defaults to organization 1 for single-org tests.
 */
final class InMemoryCompanySettingsRepository implements CompanySettingsRepositoryInterface
{
    /** @var array<int, CompanySettings> */
    private array $byOrganization = [];

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

    public function find(): ?CompanySettings
    {
        return $this->byOrganization[$this->orgId->get()] ?? null;
    }

    public function save(CompanySettings $settings): void
    {
        // The organization is forced from the request-scoped holder.
        $organizationId = $this->orgId->get();
        $this->byOrganization[$organizationId] = new CompanySettings(
            organizationId: $organizationId,
            legalName: $settings->legalName,
            address: $settings->address,
            phone: $settings->phone,
            email: $settings->email,
            registrationNumber: $settings->registrationNumber,
            bankName: $settings->bankName,
            bankBranch: $settings->bankBranch,
            accountType: $settings->accountType,
            accountNumber: $settings->accountNumber,
            logoUrl: $settings->logoUrl,
            createdAt: $settings->createdAt,
            updatedAt: $settings->updatedAt,
        );
    }
}
