<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Support;

use NeneInvoice\PaymentLink\PaymentLink;
use NeneInvoice\PaymentLink\PaymentLinkRepositoryInterface;
use NeneInvoice\PaymentLink\PaymentLinkStatus;

/**
 * In-memory {@see PaymentLinkRepositoryInterface} for use-case tests. Org scope
 * is supplied at construction so the double mirrors the org-scoped PDO repo.
 */
final class InMemoryPaymentLinkRepository implements PaymentLinkRepositoryInterface
{
    /** @var array<int, PaymentLink> */
    private array $byId = [];

    private int $nextId = 1;

    public function __construct(private readonly int $organizationId = 1)
    {
    }

    public function save(PaymentLink $link): int
    {
        $id              = $this->nextId++;
        $this->byId[$id] = new PaymentLink(
            organizationId: $link->organizationId,
            invoiceId: $link->invoiceId,
            tokenHash: $link->tokenHash,
            gateway: $link->gateway,
            status: $link->status,
            expiresAt: $link->expiresAt,
            gatewaySessionId: $link->gatewaySessionId,
            paidAt: $link->paidAt,
            revokedAt: $link->revokedAt,
            id: $id,
            createdAt: $link->createdAt,
            updatedAt: $link->updatedAt,
        );

        return $id;
    }

    public function findActiveByInvoiceId(int $invoiceId): ?PaymentLink
    {
        foreach ($this->byId as $link) {
            if ($link->invoiceId === $invoiceId
                && $link->organizationId === $this->organizationId
                && $link->status === PaymentLinkStatus::Active) {
                return $link;
            }
        }

        return null;
    }

    public function findById(int $id): ?PaymentLink
    {
        $link = $this->byId[$id] ?? null;

        return $link !== null && $link->organizationId === $this->organizationId ? $link : null;
    }

    public function findByHash(string $tokenHash): ?PaymentLink
    {
        foreach ($this->byId as $link) {
            if ($link->tokenHash === $tokenHash) {
                return $link;
            }
        }

        return null;
    }

    public function findByGatewaySessionId(string $gatewaySessionId): ?PaymentLink
    {
        foreach ($this->byId as $link) {
            if ($link->gatewaySessionId === $gatewaySessionId) {
                return $link;
            }
        }

        return null;
    }

    public function markRevoked(int $id, string $revokedAt): bool
    {
        $link = $this->byId[$id] ?? null;

        if ($link === null
            || $link->organizationId !== $this->organizationId
            || $link->status !== PaymentLinkStatus::Active) {
            return false;
        }

        $this->byId[$id] = new PaymentLink(
            organizationId: $link->organizationId,
            invoiceId: $link->invoiceId,
            tokenHash: $link->tokenHash,
            gateway: $link->gateway,
            status: PaymentLinkStatus::Revoked,
            expiresAt: $link->expiresAt,
            gatewaySessionId: $link->gatewaySessionId,
            paidAt: $link->paidAt,
            revokedAt: $revokedAt,
            id: $link->id,
            createdAt: $link->createdAt,
            updatedAt: $revokedAt,
        );

        return true;
    }

    public function markPaid(int $id, string $gatewaySessionId, string $paidAt): bool
    {
        $link = $this->byId[$id] ?? null;

        if ($link === null
            || $link->organizationId !== $this->organizationId
            || $link->status !== PaymentLinkStatus::Active) {
            return false;
        }

        $this->byId[$id] = new PaymentLink(
            organizationId: $link->organizationId,
            invoiceId: $link->invoiceId,
            tokenHash: $link->tokenHash,
            gateway: $link->gateway,
            status: PaymentLinkStatus::Paid,
            expiresAt: $link->expiresAt,
            gatewaySessionId: $gatewaySessionId,
            paidAt: $paidAt,
            revokedAt: $link->revokedAt,
            id: $link->id,
            createdAt: $link->createdAt,
            updatedAt: $paidAt,
        );

        return true;
    }
}
