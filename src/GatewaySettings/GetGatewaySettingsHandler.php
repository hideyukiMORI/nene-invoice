<?php

declare(strict_types=1);

namespace NeneInvoice\GatewaySettings;

use Nene2\Http\JsonResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `GET /admin/gateway-settings` — reports whether the PAY.JP gateway is
 * configured (from the environment), without ever returning the secret key.
 * Capability: ManageCompanySettings (via CapabilityResolver).
 */
final readonly class GetGatewaySettingsHandler implements RequestHandlerInterface
{
    public function __construct(
        private JsonResponseFactory $json,
        private string $publicKey,
        private bool $secretSet,
        private bool $webhookSet,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->json->create(
            GatewaySettingsResponse::toArray('payjp', $this->publicKey, $this->secretSet, $this->webhookSet),
            200,
        );
    }
}
