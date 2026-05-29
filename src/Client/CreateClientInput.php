<?php

declare(strict_types=1);

namespace NeneInvoice\Client;

final readonly class CreateClientInput
{
    public function __construct(
        public string $name,
        public ?string $contactName = null,
        public ?string $email = null,
        public ?string $billingAddress = null,
        public ?string $registrationNumber = null,
    ) {
    }
}
