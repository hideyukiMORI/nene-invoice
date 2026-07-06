<?php

declare(strict_types=1);

namespace NeneInvoice\Company;

use Closure;
use LogicException;
use Nene2\Audit\AuditEvent;
use Nene2\Audit\AuditRecorderFactoryInterface;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Compliance\RegistrationNumber;

final readonly class UpdateCompanySettingsUseCase implements UpdateCompanySettingsUseCaseInterface
{
    /**
     * @param Closure(DatabaseQueryExecutorInterface): CompanySettingsRepositoryInterface $repositoryFactory
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private CompanySettingsRepositoryInterface $repository,
        private DatabaseTransactionManagerInterface $tx,
        private Closure $repositoryFactory,
        private AuditRecorderFactoryInterface $auditFactory,
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

        return $this->tx->transactional(function (DatabaseQueryExecutorInterface $exec) use ($actorUserId, $organizationId, $input, $existing): CompanySettings {
            $repository = ($this->repositoryFactory)($exec);

            $repository->save(new CompanySettings(
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
                defaultQuoteValidityDays: $input->defaultQuoteValidityDays,
                defaultPaymentClosingDay: $input->defaultPaymentClosingDay,
                defaultPaymentMonthOffset: $input->defaultPaymentMonthOffset,
                defaultPaymentPayDay: $input->defaultPaymentPayDay,
                pdfTemplate: $input->pdfTemplate,
                pdfSpacing: $input->pdfSpacing,
                pdfHeadingFont: $input->pdfHeadingFont,
            ));

            $saved = $repository->find();

            if ($saved === null) {
                throw new LogicException('Company settings disappeared immediately after save.');
            }

            $this->auditFactory->forExecutor($exec)->record(new AuditEvent(
                action: $existing === null ? 'company_settings.created' : 'company_settings.updated',
                entityType: 'company_settings',
                entityId: $organizationId,
                actorId: $actorUserId,
                organizationId: $organizationId,
                before: $existing !== null ? CompanySettingsResponse::toArray($existing) : null,
                after: CompanySettingsResponse::toArray($saved),
            ));

            return $saved;
        });
    }
}
