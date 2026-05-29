<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

use DomainException;

/**
 * Thrown when an invoice is being issued as a qualified invoice (適格請求書) but
 * a statutorily required field is missing — e.g. the issuer registration number
 * (accounting-compliance.md §2/§4).
 */
final class QualifiedInvoiceIncompleteException extends DomainException
{
}
