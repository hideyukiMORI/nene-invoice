<?php

declare(strict_types=1);

namespace NeneInvoice\Company;

interface GetCompanySettingsUseCaseInterface
{
    public function execute(): CompanySettings;
}
