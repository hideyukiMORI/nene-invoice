<?php

declare(strict_types=1);

namespace NeneInvoice\Company;

use Nene2\Database\DatabaseQueryExecutorInterface;

final readonly class PdoCompanySettingsRepository implements CompanySettingsRepositoryInterface
{
    private const COLUMNS = 'organization_id, legal_name, address, phone, email, registration_number, bank_name, bank_branch, account_type, account_number, logo_url, created_at, updated_at';

    public function __construct(
        private DatabaseQueryExecutorInterface $query,
    ) {
    }

    public function findByOrganization(int $organizationId): ?CompanySettings
    {
        $row = $this->query->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM company_settings WHERE organization_id = ?',
            [$organizationId],
        );

        return $row !== null ? $this->mapRow($row) : null;
    }

    public function save(CompanySettings $settings): void
    {
        $now = date('Y-m-d H:i:s');
        $exists = $this->findByOrganization($settings->organizationId) !== null;

        if ($exists) {
            $this->query->execute(
                'UPDATE company_settings SET legal_name = ?, address = ?, phone = ?, email = ?, registration_number = ?, bank_name = ?, bank_branch = ?, account_type = ?, account_number = ?, logo_url = ?, updated_at = ? WHERE organization_id = ?',
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
                    $now,
                    $settings->organizationId,
                ],
            );

            return;
        }

        $this->query->execute(
            'INSERT INTO company_settings (organization_id, legal_name, address, phone, email, registration_number, bank_name, bank_branch, account_type, account_number, logo_url, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $settings->organizationId,
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
            createdAt: (string) $row['created_at'],
            updatedAt: (string) $row['updated_at'],
        );
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
