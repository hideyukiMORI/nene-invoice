<?php

declare(strict_types=1);

namespace NeneInvoice\Company;

/**
 * Serializes {@see CompanySettings} to its snake_case JSON representation.
 */
final class CompanySettingsResponse
{
    /** @return array<string, mixed> */
    public static function toArray(CompanySettings $settings): array
    {
        return [
            'organization_id' => $settings->organizationId,
            'legal_name' => $settings->legalName,
            'address' => $settings->address,
            'phone' => $settings->phone,
            'email' => $settings->email,
            'registration_number' => $settings->registrationNumber,
            'bank_name' => $settings->bankName,
            'bank_branch' => $settings->bankBranch,
            'account_type' => $settings->accountType,
            'account_number' => $settings->accountNumber,
            'logo_url' => $settings->logoUrl,
            'created_at' => $settings->createdAt,
            'updated_at' => $settings->updatedAt,
        ];
    }
}
