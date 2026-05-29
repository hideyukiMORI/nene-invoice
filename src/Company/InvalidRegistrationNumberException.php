<?php

declare(strict_types=1);

namespace NeneInvoice\Company;

use DomainException;

/**
 * Thrown when the issuer's registration number is present but malformed. The
 * rule lives in {@see \NeneInvoice\Compliance\RegistrationNumber}.
 */
final class InvalidRegistrationNumberException extends DomainException
{
    public function __construct(string $value)
    {
        parent::__construct(sprintf('Registration number "%s" must be "T" followed by 13 digits.', $value));
    }
}
