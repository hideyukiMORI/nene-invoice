<?php

declare(strict_types=1);

namespace NeneInvoice\Company;

use Nene2\Http\RequestScopedHolder;

final readonly class GetCompanySettingsUseCase
{
    /**
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private CompanySettingsRepositoryInterface $repository,
        private RequestScopedHolder $orgId,
    ) {
    }

    /** @throws CompanySettingsNotFoundException */
    public function execute(): CompanySettings
    {
        $settings = $this->repository->find();

        if ($settings === null) {
            throw new CompanySettingsNotFoundException($this->orgId->get());
        }

        return $settings;
    }
}
