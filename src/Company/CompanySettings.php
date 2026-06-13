<?php

declare(strict_types=1);

namespace NeneInvoice\Company;

use DateTimeImmutable;

/**
 * Issuer (自社) profile for one organization — the supplier side of a qualified
 * invoice. One record per organization.
 *
 * `registrationNumber` is the issuer's Japan invoice registration number
 * (登録番号, `T` + 13 digits). It may be null until the operator registers; an
 * invoice can only be marked qualified when it is present (enforced later, at
 * invoice level — see accounting-compliance.md).
 *
 * Billing defaults (Issue #268) are all nullable — "no default" by default.
 * `defaultQuoteValidityDays` drives the quote 有効期限. The three payment fields
 * form the 締め日 ＋ 支払サイト model; `defaultPaymentMonthOffset` is the presence
 * flag (null = no payment-terms default), while null closing/pay day = 末日.
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
        public ?int $defaultQuoteValidityDays = null,
        public ?int $defaultPaymentClosingDay = null,
        public ?int $defaultPaymentMonthOffset = null,
        public ?int $defaultPaymentPayDay = null,
        public string $pdfTemplate = 'standard',
        public string $pdfSpacing = 'medium',
        public string $pdfHeadingFont = 'gothic',
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
    ) {
    }

    /** The configured payment terms, or null when no default is set. */
    public function paymentTerms(): ?PaymentTerms
    {
        if ($this->defaultPaymentMonthOffset === null) {
            return null;
        }

        return new PaymentTerms(
            $this->defaultPaymentClosingDay,
            $this->defaultPaymentMonthOffset,
            $this->defaultPaymentPayDay,
        );
    }

    /** Default quote validity end date (`Y-m-d`) from an issue date, or null. */
    public function quoteValidUntilFrom(DateTimeImmutable $issueDate): ?string
    {
        if ($this->defaultQuoteValidityDays === null) {
            return null;
        }

        return $issueDate->modify('+' . $this->defaultQuoteValidityDays . ' day')->format('Y-m-d');
    }
}
