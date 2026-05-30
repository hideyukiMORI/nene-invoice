<?php

declare(strict_types=1);

namespace NeneInvoice\InvoiceDownloadToken;

/**
 * A time-limited, hashed token that lets a client download one invoice PDF
 * without logging in. The raw token is only ever held in memory and in the
 * URL; the database stores only the SHA-256 hash.
 */
final readonly class InvoiceDownloadToken
{
    public function __construct(
        public int $invoiceId,
        public int $organizationId,
        public string $tokenHash,
        public string $expiresAt,
        public string $createdAt,
        public ?int $id = null,
    ) {
    }

    public function isExpired(string $now): bool
    {
        return $this->expiresAt <= $now;
    }
}
