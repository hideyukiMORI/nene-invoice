<?php

declare(strict_types=1);

namespace NeneInvoice\Mailer;

/** Value object for a single outgoing email. */
final readonly class MailMessage
{
    /**
     * @param string      $toAddress Recipient email address
     * @param string      $toName    Recipient display name (may be empty)
     * @param string      $subject   Email subject
     * @param string      $bodyHtml  HTML body (plain-text alt-body derived from it)
     * @param string|null $attachmentBytes Raw bytes of an attachment (e.g. PDF)
     * @param string|null $attachmentName Filename shown to the recipient
     * @param string|null $attachmentMime MIME type of the attachment
     */
    public function __construct(
        public string $toAddress,
        public string $toName,
        public string $subject,
        public string $bodyHtml,
        public ?string $attachmentBytes = null,
        public ?string $attachmentName = null,
        public ?string $attachmentMime = null,
    ) {
    }
}
