<?php

declare(strict_types=1);

namespace NeneInvoice\Client;

/**
 * A buyer (取引先) in the billing system, scoped to one organization.
 *
 * `registrationNumber` is the buyer's Japan invoice registration number
 * (optional; format `T` + 13 digits when present — see accounting-compliance).
 * Clients are soft-deleted so issued documents that reference them stay intact.
 */
final readonly class Client
{
    public function __construct(
        public int $organizationId,
        public string $name,
        public ?string $contactName = null,
        public ?string $email = null,
        public ?string $billingAddress = null,
        public ?string $registrationNumber = null,
        public bool $isDeleted = false,
        public ?int $id = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
    ) {
    }
}
