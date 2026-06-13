<?php

declare(strict_types=1);

namespace NeneInvoice\Company;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Http\RequestScopedHolder;

final readonly class PdoCompanySettingsRepository implements CompanySettingsRepositoryInterface
{
    private const COLUMNS = 'organization_id, legal_name, address, phone, email, registration_number, bank_name, bank_branch, account_type, account_number, logo_url, default_quote_validity_days, default_payment_closing_day, default_payment_month_offset, default_payment_pay_day, pdf_template, pdf_spacing, pdf_heading_font, created_at, updated_at';

    /**
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private DatabaseQueryExecutorInterface $query,
        private RequestScopedHolder $orgId,
    ) {
    }

    public function find(): ?CompanySettings
    {
        $row = $this->query->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM company_settings WHERE organization_id = ?',
            [$this->orgId->get()],
        );

        return $row !== null ? $this->mapRow($row) : null;
    }

    public function save(CompanySettings $settings): void
    {
        $now = date('Y-m-d H:i:s');
        // The organization is forced from the request-scoped holder.
        $organizationId = $this->orgId->get();
        $exists = $this->find() !== null;

        if ($exists) {
            $this->query->execute(
                'UPDATE company_settings SET legal_name = ?, address = ?, phone = ?, email = ?, registration_number = ?, bank_name = ?, bank_branch = ?, account_type = ?, account_number = ?, logo_url = ?, default_quote_validity_days = ?, default_payment_closing_day = ?, default_payment_month_offset = ?, default_payment_pay_day = ?, pdf_template = ?, pdf_spacing = ?, pdf_heading_font = ?, updated_at = ? WHERE organization_id = ?',
                [
                    $settings->legalName,
                    $settings->address,
                    $settings->phone,
                    $settings->email,
                    $settings->registrationNumber,
                    $settings->bankName,
                    $settings->bankBranch,
                    $settings->accountType,
                    $settings->accountNumber,
                    $settings->logoUrl,
                    $settings->defaultQuoteValidityDays,
                    $settings->defaultPaymentClosingDay,
                    $settings->defaultPaymentMonthOffset,
                    $settings->defaultPaymentPayDay,
                    $settings->pdfTemplate,
                    $settings->pdfSpacing,
                    $settings->pdfHeadingFont,
                    $now,
                    $organizationId,
                ],
            );

            return;
        }

        $this->query->execute(
            'INSERT INTO company_settings (organization_id, legal_name, address, phone, email, registration_number, bank_name, bank_branch, account_type, account_number, logo_url, default_quote_validity_days, default_payment_closing_day, default_payment_month_offset, default_payment_pay_day, pdf_template, pdf_spacing, pdf_heading_font, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $organizationId,
                $settings->legalName,
                $settings->address,
                $settings->phone,
                $settings->email,
                $settings->registrationNumber,
                $settings->bankName,
                $settings->bankBranch,
                $settings->accountType,
                $settings->accountNumber,
                $settings->logoUrl,
                $settings->defaultQuoteValidityDays,
                $settings->defaultPaymentClosingDay,
                $settings->defaultPaymentMonthOffset,
                $settings->defaultPaymentPayDay,
                $settings->pdfTemplate,
                $settings->pdfSpacing,
                $settings->pdfHeadingFont,
                $now,
                $now,
            ],
        );
    }

    /** @param array<string, mixed> $row */
    private function mapRow(array $row): CompanySettings
    {
        return new CompanySettings(
            organizationId: (int) $row['organization_id'],
            legalName: (string) $row['legal_name'],
            address: $this->nullableString($row['address'] ?? null),
            phone: $this->nullableString($row['phone'] ?? null),
            email: $this->nullableString($row['email'] ?? null),
            registrationNumber: $this->nullableString($row['registration_number'] ?? null),
            bankName: $this->nullableString($row['bank_name'] ?? null),
            bankBranch: $this->nullableString($row['bank_branch'] ?? null),
            accountType: $this->nullableString($row['account_type'] ?? null),
            accountNumber: $this->nullableString($row['account_number'] ?? null),
            logoUrl: $this->nullableString($row['logo_url'] ?? null),
            defaultQuoteValidityDays: $this->nullableInt($row['default_quote_validity_days'] ?? null),
            defaultPaymentClosingDay: $this->nullableInt($row['default_payment_closing_day'] ?? null),
            defaultPaymentMonthOffset: $this->nullableInt($row['default_payment_month_offset'] ?? null),
            defaultPaymentPayDay: $this->nullableInt($row['default_payment_pay_day'] ?? null),
            pdfTemplate: $this->stringOrDefault($row['pdf_template'] ?? null, 'standard'),
            pdfSpacing: $this->stringOrDefault($row['pdf_spacing'] ?? null, 'medium'),
            pdfHeadingFont: $this->stringOrDefault($row['pdf_heading_font'] ?? null, 'gothic'),
            createdAt: (string) $row['created_at'],
            updatedAt: (string) $row['updated_at'],
        );
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function stringOrDefault(mixed $value, string $default): string
    {
        return is_string($value) && $value !== '' ? $value : $default;
    }
}
