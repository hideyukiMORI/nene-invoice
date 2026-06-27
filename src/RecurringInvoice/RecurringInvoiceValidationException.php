<?php

declare(strict_types=1);

namespace NeneInvoice\RecurringInvoice;

use DomainException;

/**
 * Thrown when recurring-invoice input is invalid (unknown client, empty lines,
 * disallowed tax rate, malformed run date, etc.). Surfaces as 422.
 */
final class RecurringInvoiceValidationException extends DomainException
{
}
