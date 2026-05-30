<?php

declare(strict_types=1);

namespace NeneInvoice\InvoiceDownloadToken;

use DateTimeImmutable;
use NeneInvoice\Invoice\InvoiceNotFoundException;
use NeneInvoice\Invoice\InvoiceRepositoryInterface;

/**
 * Generates a time-limited download token for one invoice and persists it.
 * Returns the raw (un-hashed) token so the caller can embed it in the URL.
 * The raw token is never stored — only its SHA-256 hash is kept in the DB.
 */
final readonly class GenerateDownloadTokenUseCase
{
    private const TTL_DAYS = 7;

    public function __construct(
        private InvoiceRepositoryInterface $invoices,
        private InvoiceDownloadTokenRepositoryInterface $tokens,
    ) {
    }

    /**
     * @return array{rawToken: string, expiresAt: string}
     * @throws InvoiceNotFoundException
     */
    public function execute(int $organizationId, int $invoiceId): array
    {
        $invoice = $this->invoices->findById($invoiceId);

        if ($invoice === null || $invoice->organizationId !== $organizationId) {
            throw new InvoiceNotFoundException($invoiceId);
        }

        $rawToken  = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $tokenHash = hash('sha256', $rawToken);
        $now       = new DateTimeImmutable();
        $expiresAt = $now->modify('+' . self::TTL_DAYS . ' days')->format('Y-m-d H:i:s');
        $createdAt = $now->format('Y-m-d H:i:s');

        $this->tokens->save(new InvoiceDownloadToken(
            invoiceId: $invoiceId,
            organizationId: $organizationId,
            tokenHash: $tokenHash,
            expiresAt: $expiresAt,
            createdAt: $createdAt,
        ));

        return ['rawToken' => $rawToken, 'expiresAt' => $expiresAt];
    }
}
