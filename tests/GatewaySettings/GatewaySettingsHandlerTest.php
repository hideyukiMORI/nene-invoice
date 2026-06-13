<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\GatewaySettings;

use Nene2\Http\JsonResponseFactory;
use NeneInvoice\GatewaySettings\GetGatewaySettingsHandler;
use NeneInvoice\GatewaySettings\TestGatewayConnectivityHandler;
use NeneInvoice\Tests\Support\FakeGateway;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

final class GatewaySettingsHandlerTest extends TestCase
{
    private Psr17Factory $psr17;

    private JsonResponseFactory $json;

    protected function setUp(): void
    {
        $this->psr17 = new Psr17Factory();
        $this->json  = new JsonResponseFactory($this->psr17, $this->psr17);
    }

    /** @return array<string, mixed> */
    private function decode(string $body): array
    {
        $decoded = json_decode($body, true);
        self::assertIsArray($decoded);

        return $decoded;
    }

    public function test_status_masks_public_key_and_never_returns_secret(): void
    {
        $handler  = new GetGatewaySettingsHandler($this->json, 'pk_test_f1983de975bc8d252fd059a6', secretSet: true, webhookSet: false);
        $response = $handler->handle($this->psr17->createServerRequest('GET', '/admin/gateway-settings'));

        self::assertSame(200, $response->getStatusCode());
        $data = $this->decode((string) $response->getBody());

        self::assertSame('payjp', $data['gateway']);
        self::assertSame('pk_test_…59a6', $data['public_key_masked']);
        self::assertTrue($data['secret_set']);
        self::assertFalse($data['webhook_token_set']);
        self::assertTrue($data['configured']);

        $encoded = json_encode($data, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('sk_', $encoded);
        self::assertStringNotContainsString('975bc8d252fd059a6', $encoded);
    }

    public function test_status_unconfigured(): void
    {
        $handler  = new GetGatewaySettingsHandler($this->json, '', secretSet: false, webhookSet: false);
        $response = $handler->handle($this->psr17->createServerRequest('GET', '/admin/gateway-settings'));

        $data = $this->decode((string) $response->getBody());
        self::assertNull($data['public_key_masked']);
        self::assertFalse($data['configured']);
    }

    public function test_connectivity_not_configured(): void
    {
        $handler  = new TestGatewayConnectivityHandler(new FakeGateway(), $this->json, secretSet: false);
        $data     = $this->decode((string) $handler->handle($this->req())->getBody());

        self::assertFalse($data['ok']);
        self::assertSame('not_configured', $data['detail']);
    }

    public function test_connectivity_connected(): void
    {
        $handler = new TestGatewayConnectivityHandler(new FakeGateway(connectivity: true), $this->json, secretSet: true);
        $data    = $this->decode((string) $handler->handle($this->req())->getBody());

        self::assertTrue($data['ok']);
        self::assertSame('connected', $data['detail']);
    }

    public function test_connectivity_invalid_credentials(): void
    {
        $handler = new TestGatewayConnectivityHandler(new FakeGateway(connectivity: false), $this->json, secretSet: true);
        $data    = $this->decode((string) $handler->handle($this->req())->getBody());

        self::assertFalse($data['ok']);
        self::assertSame('invalid_credentials', $data['detail']);
    }

    public function test_connectivity_unreachable(): void
    {
        $handler = new TestGatewayConnectivityHandler(new FakeGateway(connectivityThrows: true), $this->json, secretSet: true);
        $data    = $this->decode((string) $handler->handle($this->req())->getBody());

        self::assertFalse($data['ok']);
        self::assertSame('unreachable', $data['detail']);
    }

    private function req(): \Psr\Http\Message\ServerRequestInterface
    {
        return $this->psr17->createServerRequest('POST', '/admin/gateway-settings/test');
    }
}
