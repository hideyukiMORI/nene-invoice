<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

use DomainException;

/**
 * Thrown for invoice operations that are not allowed in the current state
 * (e.g. issuing a non-draft invoice, or one with no line items). Surfaces as 422.
 */
final class InvoiceValidationException extends DomainException
{
}
