<?php

declare(strict_types=1);

namespace NeneInvoice\Company;

use LogicException;
use NeneInvoice\Compliance\RegistrationNumber;

final readonly class UpdateCompanySettingsUseCase
{
    public function __construct(
        private CompanySettingsRepositoryInterface $repository,
    ) {
    }

    /**
     * Upserts the issuer profile for the caller's organization.
     *
     * @throws InvalidRegistrationNumberException
     */
    public function execute(int $organizationId, UpdateCompanySettingsInput $input): CompanySettings
    {
        if ($input->registrationNumber !== null && !RegistrationNumber::isValid($input->registrationNumber)) {
            throw new InvalidRegistrationNumberException($input->registrationNumber);
        }

        $this->repository->save(new CompanySettings(
            organizationId: $organizationId,
            legalName: $input->legalName,
            address: $input->address,
            phone: $input->phone,
            email: $input->email,
            registrationNumber: $input->registrationNumber,
            bankName: $input->bankName,
            bankBranch: $input->bankBranch,
            accountType: $input->accountType,
            accountNumber: $input->accountNumber,
            logoUrl: $input->logoUrl,
        ));

        $saved = $this->repository->findByOrganization($organizationId);

        if ($saved === null) {
            throw new LogicException('Company settings disappeared immediately after save.');
        }

        return $saved;
    }
}
