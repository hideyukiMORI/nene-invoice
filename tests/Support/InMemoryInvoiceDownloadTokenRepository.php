<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Support;

use NeneInvoice\InvoiceDownloadToken\InvoiceDownloadToken;
use NeneInvoice\InvoiceDownloadToken\InvoiceDownloadTokenRepositoryInterface;

final class InMemoryInvoiceDownloadTokenRepository implements InvoiceDownloadTokenRepositoryInterface
{
    /** @var array<int, InvoiceDownloadToken> */
    private array $byId = [];

    private int $nextId = 1;

    public function save(InvoiceDownloadToken $token): int
    {
        $id             = $this->nextId++;
        $this->byId[$id] = new InvoiceDownloadToken(
            invoiceId: $token->invoiceId,
            organizationId: $token->organizationId,
            tokenHash: $token->tokenHash,
            expiresAt: $token->expiresAt,
            createdAt: $token->createdAt,
            id: $id,
        );

        return $id;
    }

    public function findByHash(string $tokenHash): ?InvoiceDownloadToken
    {
        foreach ($this->byId as $token) {
            if ($token->tokenHash === $tokenHash) {
                return $token;
            }
        }

        return null;
    }
}
