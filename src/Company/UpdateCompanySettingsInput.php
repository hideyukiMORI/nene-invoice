<?php

declare(strict_types=1);

namespace NeneInvoice\Company;

final readonly class UpdateCompanySettingsInput
{
    public function __construct(
        public string $legalName,
        public ?string $address = null,
        public ?string $phone = null,
        public ?string $email = null,
        public ?string $registrationNumber = null,
        public ?string $bankName = null,
        public ?string $bankBranch = null,
        public ?string $accountType = null,
        public ?string $accountNumber = null,
        public ?string $logoUrl = null,
        public ?int $defaultQuoteValidityDays = null,
        public ?int $defaultPaymentClosingDay = null,
        public ?int $defaultPaymentMonthOffset = null,
        public ?int $defaultPaymentPayDay = null,
    ) {
    }
}
