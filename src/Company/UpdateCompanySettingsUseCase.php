<?php

declare(strict_types=1);

namespace NeneInvoice\Company;

use LogicException;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Audit\AuditRecorderInterface;
use NeneInvoice\Compliance\RegistrationNumber;

final readonly class UpdateCompanySettingsUseCase
{
    /**
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private CompanySettingsRepositoryInterface $repository,
        private AuditRecorderInterface $audit,
        private RequestScopedHolder $orgId,
    ) {
    }

    /**
     * Upserts the issuer profile for the caller's organization.
     *
     * @throws InvalidRegistrationNumberException
     */
    public function execute(?int $actorUserId, UpdateCompanySettingsInput $input): CompanySettings
    {
        if ($input->registrationNumber !== null && !RegistrationNumber::isValid($input->registrationNumber)) {
            throw new InvalidRegistrationNumberException($input->registrationNumber);
        }

        $organizationId = $this->orgId->get();
        $existing = $this->repository->find();

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

        $saved = $this->repository->find();

        if ($saved === null) {
            throw new LogicException('Company settings disappeared immediately after save.');
        }

        $this->audit->record(
            $actorUserId,
            $organizationId,
            $existing === null ? 'company_settings.created' : 'company_settings.updated',
            'company_settings',
            $organizationId,
            $existing !== null ? CompanySettingsResponse::toArray($existing) : null,
            CompanySettingsResponse::toArray($saved),
        );

        return $saved;
    }
}
