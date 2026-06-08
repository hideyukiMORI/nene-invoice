<?php

declare(strict_types=1);

namespace NeneInvoice\ServiceToken;

use Closure;
use Nene2\Auth\TokenIssuerInterface;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\ClockInterface;
use Nene2\Http\RequestScopedHolder;
use Nene2\Http\SecureTokenHelper;
use NeneInvoice\Audit\AuditRecorderInterface;

/**
 * Issues a NeNe Clear service token (ADR 0009): mints a stateless HMAC JWT and
 * persists a registry row keyed by its `jti`, so the token can later be listed
 * and revoked. The token value itself is never stored.
 *
 * The registry write and its audit record commit atomically (ADR 0008). The
 * plaintext token is returned exactly once and never written to the audit trail.
 */
final readonly class IssueServiceTokenUseCase implements IssueServiceTokenUseCaseInterface
{
    /** Bytes of entropy for the `jti` (→ 32 hex chars). */
    private const JTI_BYTES = 16;

    /**
     * @param Closure(DatabaseQueryExecutorInterface): ServiceTokenRepositoryInterface $tokensFactory
     * @param Closure(DatabaseQueryExecutorInterface): AuditRecorderInterface $auditFactory
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private TokenIssuerInterface $issuer,
        private DatabaseTransactionManagerInterface $tx,
        private Closure $tokensFactory,
        private Closure $auditFactory,
        private ClockInterface $clock,
        private RequestScopedHolder $orgId,
    ) {
    }

    public function execute(?int $actorUserId, IssueServiceTokenInput $input): IssueServiceTokenResult
    {
        $organizationId = $this->orgId->get();
        $jti            = SecureTokenHelper::generate(self::JTI_BYTES);

        $now      = $this->clock->now();
        $issuedAt = $now->getTimestamp();
        $expires  = $issuedAt + $input->ttlSeconds;

        $plaintext = $this->issuer->issue([
            'sub'    => $input->subject,
            'org'    => $organizationId,
            'scopes' => $input->scopes,
            'jti'    => $jti,
            'iat'    => $issuedAt,
            'exp'    => $expires,
        ]);

        $token = new ServiceToken(
            id: null,
            organizationId: $organizationId,
            jti: $jti,
            subject: $input->subject,
            label: $input->label,
            scopes: $input->scopes,
            createdBy: $actorUserId,
            createdAt: $now->format('Y-m-d H:i:s'),
            expiresAt: $now->modify('+' . $input->ttlSeconds . ' seconds')->format('Y-m-d H:i:s'),
            revokedAt: null,
        );

        $id = $this->tx->transactional(function (DatabaseQueryExecutorInterface $exec) use ($token, $actorUserId, $organizationId): int {
            $newId = ($this->tokensFactory)($exec)->save($token);

            // Audit (ADR 0008): issuing a machine credential is security-relevant.
            // `after` carries only non-secret metadata — never the token or its jti.
            ($this->auditFactory)($exec)->record(
                $actorUserId,
                $organizationId,
                'service_token.issued',
                'service_token',
                $newId,
                null,
                ['label' => $token->label, 'subject' => $token->subject, 'scopes' => $token->scopes, 'expires_at' => $token->expiresAt],
            );

            return $newId;
        });

        return new IssueServiceTokenResult(
            token: new ServiceToken(
                id: $id,
                organizationId: $token->organizationId,
                jti: $token->jti,
                subject: $token->subject,
                label: $token->label,
                scopes: $token->scopes,
                createdBy: $token->createdBy,
                createdAt: $token->createdAt,
                expiresAt: $token->expiresAt,
                revokedAt: null,
            ),
            plaintextToken: $plaintext,
        );
    }
}
