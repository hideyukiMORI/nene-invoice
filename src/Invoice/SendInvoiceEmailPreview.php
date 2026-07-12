<?php

declare(strict_types=1);

namespace NeneInvoice\Invoice;

/**
 * Preview of the email that would be sent for an invoice, without actually
 * sending it. Used by demo organizations (whose fictitious `.example` clients
 * are undeliverable): the send action returns this content for display in a
 * modal instead of dispatching a message. See #626.
 *
 * `recipient` / `subject` / `bodyHtml` map to the JSON fields `recipient`,
 * `subject`, `body_html` (用語レジストリ登録済み). No PDF is attached to a
 * preview; the body text already notes that a PDF would be enclosed.
 */
final readonly class SendInvoiceEmailPreview
{
    public function __construct(
        public string $recipient,
        public string $subject,
        public string $bodyHtml,
    ) {
    }
}
