<?php

declare(strict_types=1);

namespace NeneInvoice\Company;

/**
 * Issuer (自社) profile for one organization — the supplier side of a qualified
 * invoice. One record per organization.
 *
 * `registrationNumber` is the issuer's Japan invoice registration number
 * (登録番号, `T` + 13 digits). It may be null until the operator registers; an
 * invoice can only be marked qualified when it is present (enforced later, at
 * invoice level — see accounting-compliance.md).
 */
final readonly class CompanySettings
{
    public function __construct(
        public int $organizationId,
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
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
    ) {
    }
}
