<?php

declare(strict_types=1);

namespace NeneInvoice\Company;

interface UpdateCompanySettingsUseCaseInterface
{
    public function execute(?int $actorUserId, UpdateCompanySettingsInput $input): CompanySettings;
}
