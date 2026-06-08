<?php

declare(strict_types=1);

namespace NeneInvoice\ServiceToken;

/**
 * Serialises service-token registry records for the operator API. The token
 * value is **never** included here — only on the issuance response, exactly once
 * (see {@see self::toCreatedArray()}).
 */
final class ServiceTokenResponse
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(ServiceToken $token): array
    {
        return [
            'id'         => $token->id,
            'subject'    => $token->subject,
            'label'      => $token->label,
            'scopes'     => $token->scopes,
            'created_by' => $token->createdBy,
            'created_at' => $token->createdAt,
            'expires_at' => $token->expiresAt,
            'revoked_at' => $token->revokedAt,
            'status'     => $token->isRevoked() ? 'revoked' : 'active',
        ];
    }

    /**
     * Issuance response: metadata plus the plaintext `token`, shown to the
     * operator once and never retrievable again.
     *
     * @return array<string, mixed>
     */
    public static function toCreatedArray(IssueServiceTokenResult $result): array
    {
        return self::toArray($result->token) + ['token' => $result->plaintextToken];
    }
}
