<?php

declare(strict_types=1);

namespace NeneInvoice\GatewaySettings;

/**
 * Read model for the gateway configuration status. Secrets are never returned —
 * only whether they are set, plus a masked public key for display. The secret
 * key and webhook token live in the environment (ADR 0013), not the database.
 */
final readonly class GatewaySettingsResponse
{
    /** @return array<string, mixed> */
    public static function toArray(string $gateway, string $publicKey, bool $secretSet, bool $webhookSet): array
    {
        return [
            'gateway'           => $gateway,
            'public_key_masked' => $publicKey !== '' ? self::mask($publicKey) : null,
            'secret_set'        => $secretSet,
            'webhook_token_set' => $webhookSet,
            'configured'        => $secretSet && $publicKey !== '',
        ];
    }

    private static function mask(string $value): string
    {
        if (strlen($value) <= 12) {
            return str_repeat('•', max(4, strlen($value)));
        }

        return substr($value, 0, 8) . '…' . substr($value, -4);
    }
}
