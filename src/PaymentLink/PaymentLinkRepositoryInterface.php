<?php

declare(strict_types=1);

namespace NeneInvoice\PaymentLink;

interface PaymentLinkRepositoryInterface
{
    public function save(PaymentLink $link): int;

    /**
     * The currently active link for an invoice in the caller's organization, if
     * any. Used to auto-revoke a prior active link when re-issuing.
     */
    public function findActiveByInvoiceId(int $invoiceId): ?PaymentLink;

    /** Org-scoped lookup by primary key (for admin revoke). */
    public function findById(int $id): ?PaymentLink;

    /**
     * Lookup by token hash, **not** organization-scoped: the public link page and
     * the settlement webhook resolve the owning organization *from* the link.
     */
    public function findByHash(string $tokenHash): ?PaymentLink;

    /**
     * Lookup by gateway charge/session id, **not** organization-scoped: the
     * settlement webhook (#431) resolves the owning organization *from* the link.
     */
    public function findByGatewaySessionId(string $gatewaySessionId): ?PaymentLink;

    /**
     * Marks an active link revoked. Org-scoped and idempotent: returns true only
     * when an active link in the caller's org transitioned to revoked.
     */
    public function markRevoked(int $id, string $revokedAt): bool;

    /**
     * Marks an active link paid and records the gateway charge id. Org-scoped and
     * idempotent: returns true only when an active link in the caller's org
     * transitioned to paid.
     */
    public function markPaid(int $id, string $gatewaySessionId, string $paidAt): bool;
}
