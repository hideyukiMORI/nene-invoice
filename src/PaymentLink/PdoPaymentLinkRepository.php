<?php

declare(strict_types=1);

namespace NeneInvoice\PaymentLink;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Http\RequestScopedHolder;

final readonly class PdoPaymentLinkRepository implements PaymentLinkRepositoryInterface
{
    private const COLUMNS = 'id, organization_id, invoice_id, token_hash, gateway, gateway_session_id, status, expires_at, paid_at, revoked_at, created_at, updated_at';

    /**
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private DatabaseQueryExecutorInterface $query,
        private RequestScopedHolder $orgId,
    ) {
    }

    public function save(PaymentLink $link): int
    {
        return $this->query->insert(
            'INSERT INTO payment_links (organization_id, invoice_id, token_hash, gateway, gateway_session_id, status, expires_at, paid_at, revoked_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $link->organizationId,
                $link->invoiceId,
                $link->tokenHash,
                $link->gateway,
                $link->gatewaySessionId,
                $link->status->value,
                $link->expiresAt,
                $link->paidAt,
                $link->revokedAt,
                $link->createdAt,
                $link->updatedAt,
            ],
        );
    }

    public function findActiveByInvoiceId(int $invoiceId): ?PaymentLink
    {
        $row = $this->query->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM payment_links WHERE invoice_id = ? AND organization_id = ? AND status = ? LIMIT 1',
            [$invoiceId, $this->orgId->get(), PaymentLinkStatus::Active->value],
        );

        return $row !== null ? $this->mapRow($row) : null;
    }

    public function findById(int $id): ?PaymentLink
    {
        $row = $this->query->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM payment_links WHERE id = ? AND organization_id = ?',
            [$id, $this->orgId->get()],
        );

        return $row !== null ? $this->mapRow($row) : null;
    }

    public function findByHash(string $tokenHash): ?PaymentLink
    {
        $row = $this->query->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM payment_links WHERE token_hash = ?',
            [$tokenHash],
        );

        return $row !== null ? $this->mapRow($row) : null;
    }

    public function findByGatewaySessionId(string $gatewaySessionId): ?PaymentLink
    {
        $row = $this->query->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM payment_links WHERE gateway_session_id = ?',
            [$gatewaySessionId],
        );

        return $row !== null ? $this->mapRow($row) : null;
    }

    public function markRevoked(int $id, string $revokedAt): bool
    {
        $affected = $this->query->execute(
            'UPDATE payment_links SET status = ?, revoked_at = ?, updated_at = ? WHERE id = ? AND organization_id = ? AND status = ?',
            [PaymentLinkStatus::Revoked->value, $revokedAt, $revokedAt, $id, $this->orgId->get(), PaymentLinkStatus::Active->value],
        );

        return $affected > 0;
    }

    public function markPaid(int $id, string $gatewaySessionId, string $paidAt): bool
    {
        $affected = $this->query->execute(
            'UPDATE payment_links SET status = ?, gateway_session_id = ?, paid_at = ?, updated_at = ? WHERE id = ? AND organization_id = ? AND status = ?',
            [PaymentLinkStatus::Paid->value, $gatewaySessionId, $paidAt, $paidAt, $id, $this->orgId->get(), PaymentLinkStatus::Active->value],
        );

        return $affected > 0;
    }

    /** @param array<string, mixed> $row */
    private function mapRow(array $row): PaymentLink
    {
        return new PaymentLink(
            organizationId: (int) $row['organization_id'],
            invoiceId: (int) $row['invoice_id'],
            tokenHash: (string) $row['token_hash'],
            gateway: (string) $row['gateway'],
            status: PaymentLinkStatus::from((string) $row['status']),
            expiresAt: (string) $row['expires_at'],
            gatewaySessionId: $row['gateway_session_id'] !== null ? (string) $row['gateway_session_id'] : null,
            paidAt: $row['paid_at'] !== null ? (string) $row['paid_at'] : null,
            revokedAt: $row['revoked_at'] !== null ? (string) $row['revoked_at'] : null,
            id: (int) $row['id'],
            createdAt: (string) $row['created_at'],
            updatedAt: (string) $row['updated_at'],
        );
    }
}
