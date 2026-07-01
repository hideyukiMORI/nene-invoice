<?php

declare(strict_types=1);

namespace NeneInvoice\Organization;

final readonly class CreateOrganizationInput
{
    /**
     * @param string|null $adminEmail    initial admin email; when both this and
     *                                    $adminPassword are set, the org's first
     *                                    admin is provisioned in the same
     *                                    transaction (both-or-neither — validated
     *                                    at the HTTP boundary).
     * @param string|null $adminPassword initial admin plaintext password
     */
    public function __construct(
        public string $name,
        public string $slug,
        public string $plan = 'free',
        public ?string $adminEmail = null,
        public ?string $adminPassword = null,
    ) {
    }
}
