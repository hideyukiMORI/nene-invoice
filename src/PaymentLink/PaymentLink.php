<?php

declare(strict_types=1);

namespace NeneInvoice\PaymentLink;

/**
 * A hashed, time-limited, revocable link that lets a payer settle one invoice on
 * a hosted payment gateway (PAY.JP — ADR 0012/0013). The raw token is only ever
 * held in memory and in the URL; the database stores only its SHA-256 hash.
 *
 * `gatewaySessionId` is null until the hosted gateway session is created (lazily,
 * on first visit to the public link). No card data is ever stored (SAQ-A).
 */
final readonly class PaymentLink
{
    public function __construct(
        public int $organizationId,
        public int $invoiceId,
        public string $tokenHash,
        public string $gateway,
        public PaymentLinkStatus $status,
        public string $expiresAt,
        public ?string $gatewaySessionId = null,
        public ?string $paidAt = null,
        public ?string $revokedAt = null,
        public ?int $id = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
    ) {
    }

    public function isExpired(string $now): bool
    {
        return $this->expiresAt <= $now;
    }

    /** A link is payable only while active and not yet expired. */
    public function isPayable(string $now): bool
    {
        return $this->status === PaymentLinkStatus::Active && !$this->isExpired($now);
    }
}
