<?php

declare(strict_types=1);

namespace NeneInvoice\Organization;

/**
 * A tenant. Each organization is an independent issuer of qualified invoices
 * with its own users, issuer profile (`company_settings`), and billing data.
 *
 * See ADR 0006 (multi-tenancy).
 */
final readonly class Organization
{
    public function __construct(
        public string $name,
        public string $slug,
        public string $plan,
        public bool $isActive,
        public ?int $id = null,
        public ?string $externalId = null,
        public ?string $customDomain = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
    ) {
    }
}
