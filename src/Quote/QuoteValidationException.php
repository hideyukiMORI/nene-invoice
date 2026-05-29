<?php

declare(strict_types=1);

namespace NeneInvoice\Quote;

use DomainException;

/**
 * Thrown when quote input is invalid (unknown client, empty lines, disallowed
 * tax rate, etc.). Surfaces as 422 Validation Failed.
 */
final class QuoteValidationException extends DomainException
{
}
