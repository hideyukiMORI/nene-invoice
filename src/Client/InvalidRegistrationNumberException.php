<?php

declare(strict_types=1);

namespace NeneInvoice\Client;

use DomainException;

/**
 * Thrown when a client's Japan invoice registration number is present but does
 * not match the required syntax `T` + 13 digits. This is a **syntax** check only
 * (it does not verify existence) — see accounting-compliance.md §4.
 */
final class InvalidRegistrationNumberException extends DomainException
{
    public const PATTERN = '/^T[0-9]{13}$/';

    public function __construct(string $value)
    {
        parent::__construct(sprintf('Registration number "%s" must be "T" followed by 13 digits.', $value));
    }
}
