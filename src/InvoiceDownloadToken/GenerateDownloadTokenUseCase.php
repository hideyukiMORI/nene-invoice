<?php

declare(strict_types=1);

namespace NeneInvoice\InvoiceDownloadToken;

use Closure;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\ClockInterface;
use Nene2\Http\RequestScopedHolder;
use Nene2\Http\SecureTokenHelper;
use NeneInvoice\Audit\AuditRecorderInterface;
use NeneInvoice\Invoice\InvoiceNotFoundException;
use NeneInvoice\Invoice\InvoiceRepositoryInterface;

/**
 * Generates a time-limited download token for one invoice and persists it.
 * Returns the raw (un-hashed) token so the caller can embed it in the URL.
 * The raw token is never stored — only its SHA-256 hash is kept in the DB.
 */
final readonly class GenerateDownloadTokenUseCase implements GenerateDownloadTokenUseCaseInterface
{
    private const TTL_DAYS = 7;

    /**
     * @param Closure(DatabaseQueryExecutorInterface): InvoiceDownloadTokenRepositoryInterface $tokensFactory
     * @param Closure(DatabaseQueryExecutorInterface): AuditRecorderInterface $auditFactory
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private InvoiceRepositoryInterface $invoices,
        private DatabaseTransactionManagerInterface $tx,
        private Closure $tokensFactory,
        private Closure $auditFactory,
        private ClockInterface $clock,
        private RequestScopedHolder $orgId,
    ) {
    }

    /**
     * @param int|null $actorUserId authenticated user who generated the token (null for system)
     *
     * @return array{rawToken: string, expiresAt: string}
     * @throws InvoiceNotFoundException
     */
    public function execute(?int $actorUserId, int $invoiceId): array
    {
        // The invoice repository is org-scoped (holder), so a foreign invoice
        // is already invisible here.
        $invoice = $this->invoices->findById($invoiceId);

        if ($invoice === null) {
            throw new InvoiceNotFoundException($invoiceId);
        }

        $organizationId = $this->orgId->get();

        // 256-bit token; only its SHA-256 hash is persisted (see SecureTokenHelper).
        [$rawToken, $tokenHash] = SecureTokenHelper::generateWithHash();
        $now       = $this->clock->now();
        $expiresAt = $now->modify('+' . self::TTL_DAYS . ' days')->format('Y-m-d H:i:s');
        $createdAt = $now->format('Y-m-d H:i:s');

        // The token insert and its audit record commit atomically (Issue #352).
        $this->tx->transactional(function (DatabaseQueryExecutorInterface $exec) use ($actorUserId, $organizationId, $invoiceId, $tokenHash, $expiresAt, $createdAt): null {
            ($this->tokensFactory)($exec)->save(new InvoiceDownloadToken(
                invoiceId: $invoiceId,
                organizationId: $organizationId,
                tokenHash: $tokenHash,
                expiresAt: $expiresAt,
                createdAt: $createdAt,
            ));

            // Audit (ADR 0008): issuing a public download link is an auditable event.
            // `after` carries only the non-secret expiry — the raw token and its hash
            // are never written to the audit trail.
            ($this->auditFactory)($exec)->record(
                $actorUserId,
                $organizationId,
                'invoice.download_token_issued',
                'invoice',
                $invoiceId,
                null,
                ['expires_at' => $expiresAt],
            );

            return null;
        });

        return ['rawToken' => $rawToken, 'expiresAt' => $expiresAt];
    }
}
