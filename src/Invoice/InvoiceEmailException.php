<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

use RuntimeException;

/** Thrown when an invoice cannot be emailed (wrong status or missing client email). */
final class InvoiceEmailException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $slug,
    ) {
        parent::__construct($message);
    }

    public static function noClientEmail(int $invoiceId): self
    {
        return new self(
            "Invoice {$invoiceId}: client has no email address.",
            'client-email-missing',
        );
    }

    public static function notIssued(int $invoiceId): self
    {
        return new self(
            "Invoice {$invoiceId} is not in a sendable status (must be issued, partially_paid, or paid).",
            'invoice-not-issued',
        );
    }
}
