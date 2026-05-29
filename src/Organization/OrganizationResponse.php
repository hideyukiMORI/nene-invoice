<?php

declare(strict_types=1);

namespace NeneInvoice\Organization;

/**
 * Serializes an {@see Organization} to its snake_case JSON representation.
 */
final class OrganizationResponse
{
    /** @return array<string, mixed> */
    public static function toArray(Organization $organization): array
    {
        return [
            'id' => $organization->id,
            'name' => $organization->name,
            'slug' => $organization->slug,
            'plan' => $organization->plan,
            'is_active' => $organization->isActive,
            'external_id' => $organization->externalId,
            'custom_domain' => $organization->customDomain,
            'created_at' => $organization->createdAt,
            'updated_at' => $organization->updatedAt,
        ];
    }
}
