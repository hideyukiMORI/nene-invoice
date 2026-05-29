<?php

declare(strict_types=1);

namespace NeneInvoice\Company;

final readonly class GetCompanySettingsUseCase
{
    public function __construct(
        private CompanySettingsRepositoryInterface $repository,
    ) {
    }

    /** @throws CompanySettingsNotFoundException */
    public function execute(int $organizationId): CompanySettings
    {
        $settings = $this->repository->findByOrganization($organizationId);

        if ($settings === null) {
            throw new CompanySettingsNotFoundException($organizationId);
        }

        return $settings;
    }
}
