<?php

declare(strict_types=1);

namespace NeneInvoice\InvoiceDownloadToken;

use Nene2\Database\DatabaseQueryExecutorInterface;

final readonly class PdoInvoiceDownloadTokenRepository implements InvoiceDownloadTokenRepositoryInterface
{
    public function __construct(private DatabaseQueryExecutorInterface $query)
    {
    }

    public function save(InvoiceDownloadToken $token): int
    {
        return $this->query->insert(
            'INSERT INTO invoice_download_tokens (invoice_id, organization_id, token_hash, expires_at, created_at) VALUES (?, ?, ?, ?, ?)',
            [$token->invoiceId, $token->organizationId, $token->tokenHash, $token->expiresAt, $token->createdAt],
        );
    }

    public function findByHash(string $tokenHash): ?InvoiceDownloadToken
    {
        $row = $this->query->fetchOne(
            'SELECT id, invoice_id, organization_id, token_hash, expires_at, created_at FROM invoice_download_tokens WHERE token_hash = ?',
            [$tokenHash],
        );

        return $row !== null ? $this->mapRow($row) : null;
    }

    /** @param array<string, mixed> $row */
    private function mapRow(array $row): InvoiceDownloadToken
    {
        return new InvoiceDownloadToken(
            invoiceId: (int) $row['invoice_id'],
            organizationId: (int) $row['organization_id'],
            tokenHash: (string) $row['token_hash'],
            expiresAt: (string) $row['expires_at'],
            createdAt: (string) $row['created_at'],
            id: (int) $row['id'],
        );
    }
}
