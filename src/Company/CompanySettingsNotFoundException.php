<?php

declare(strict_types=1);

namespace NeneInvoice\Company;

use RuntimeException;

final class CompanySettingsNotFoundException extends RuntimeException
{
    public function __construct(int $organizationId)
    {
        parent::__construct("Company settings for organization {$organizationId} have not been configured.");
    }
}
