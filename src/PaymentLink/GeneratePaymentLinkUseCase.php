<?php

declare(strict_types=1);

namespace NeneInvoice\PaymentLink;

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
 * Generates a time-limited, hashed payment link for one invoice and persists it.
 * Returns the raw (un-hashed) token so the caller can embed it in the URL — the
 * raw token is never stored, only its SHA-256 hash (see {@see SecureTokenHelper}).
 *
 * Re-issuing auto-revokes any prior active link for the same invoice, so at most
 * one link per invoice is payable (ADR 0012 §3). The launch gateway is PAY.JP
 * (ADR 0013); `gateway_session_id` is created lazily and stays null here.
 */
final readonly class GeneratePaymentLinkUseCase implements GeneratePaymentLinkUseCaseInterface
{
    private const TTL_DAYS = 7;

    /** Launch gateway — ADR 0013. Registered in `docs/explanation/terminology.md`. */
    public const DEFAULT_GATEWAY = 'payjp';

    /**
     * @param Closure(DatabaseQueryExecutorInterface): PaymentLinkRepositoryInterface $linksFactory
     * @param Closure(DatabaseQueryExecutorInterface): AuditRecorderInterface $auditFactory
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private InvoiceRepositoryInterface $invoices,
        private DatabaseTransactionManagerInterface $tx,
        private Closure $linksFactory,
        private Closure $auditFactory,
        private ClockInterface $clock,
        private RequestScopedHolder $orgId,
    ) {
    }

    /**
     * @param int|null $actorUserId authenticated user who generated the link (null for system)
     *
     * @return array{rawToken: string, expiresAt: string, paymentLinkId: int}
     * @throws InvoiceNotFoundException
     */
    public function execute(?int $actorUserId, int $invoiceId): array
    {
        // The invoice repository is org-scoped (holder), so a foreign invoice is
        // already invisible here.
        $invoice = $this->invoices->findById($invoiceId);

        if ($invoice === null) {
            throw new InvoiceNotFoundException($invoiceId);
        }

        $organizationId = $this->orgId->get();

        // 256-bit token; only its SHA-256 hash is persisted.
        [$rawToken, $tokenHash] = SecureTokenHelper::generateWithHash();
        $now       = $this->clock->now();
        $timestamp = $now->format('Y-m-d H:i:s');
        $expiresAt = $now->modify('+' . self::TTL_DAYS . ' days')->format('Y-m-d H:i:s');

        // Link insert, prior-link revoke, and audit commit atomically.
        $linkId = $this->tx->transactional(function (DatabaseQueryExecutorInterface $exec) use ($actorUserId, $organizationId, $invoiceId, $tokenHash, $expiresAt, $timestamp): int {
            $links = ($this->linksFactory)($exec);

            // At most one payable link per invoice: auto-revoke the prior active one.
            $prior = $links->findActiveByInvoiceId($invoiceId);
            if ($prior !== null && $prior->id !== null) {
                $links->markRevoked($prior->id, $timestamp);
            }

            $linkId = $links->save(new PaymentLink(
                organizationId: $organizationId,
                invoiceId: $invoiceId,
                tokenHash: $tokenHash,
                gateway: self::DEFAULT_GATEWAY,
                status: PaymentLinkStatus::Active,
                expiresAt: $expiresAt,
                createdAt: $timestamp,
                updatedAt: $timestamp,
            ));

            // Audit (ADR 0008): issuing a public payment link is auditable. `after`
            // carries only non-secret metadata — the raw token and its hash are
            // never written to the audit trail.
            ($this->auditFactory)($exec)->record(
                $actorUserId,
                $organizationId,
                'payment_link.issued',
                'payment_link',
                $linkId,
                null,
                ['invoice_id' => $invoiceId, 'gateway' => self::DEFAULT_GATEWAY, 'expires_at' => $expiresAt],
            );

            return $linkId;
        });

        return ['rawToken' => $rawToken, 'expiresAt' => $expiresAt, 'paymentLinkId' => $linkId];
    }
}
