<?php

declare(strict_types=1);

namespace NeneInvoice\BankTransaction;

/**
 * A learned mapping from a normalized bank remitter name to a client (#505),
 * so a future deposit from the same payer reconciles automatically. The key is
 * {@see PayerNameNormalizer}-normalized and unique per organization. This is
 * matching metadata, not a billing record.
 */
final readonly class PayerAlias
{
    public function __construct(
        public int $organizationId,
        public string $normalizedName,
        public int $clientId,
        public ?int $id = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
    ) {
    }
}
