<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\ServiceApi;

use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use NeneInvoice\Client\Client;
use NeneInvoice\ServiceApi\GetServiceClientHandler;
use NeneInvoice\Tests\Support\InMemoryClientRepository;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

final class GetServiceClientHandlerTest extends TestCase
{
    private Psr17Factory $psr17;

    private InMemoryClientRepository $clients;

    private GetServiceClientHandler $handler;

    protected function setUp(): void
    {
        $this->psr17    = new Psr17Factory();
        $this->clients  = new InMemoryClientRepository();
        $this->handler  = new GetServiceClientHandler(
            $this->clients,
            new JsonResponseFactory($this->psr17, $this->psr17),
            new ProblemDetailsResponseFactory($this->psr17, $this->psr17, 'https://nene-invoice.dev/problems/'),
        );
    }

    public function test_returns_client_contact_data_for_service_caller(): void
    {
        $id = $this->clients->save(new Client(
            organizationId: 1,
            name: '株式会社テスト',
            contactName: '山田太郎',
            email: 'yamada@example.com',
            billingAddress: '東京都渋谷区1-1-1',
            registrationNumber: 'T1234567890123',
        ));

        $request = $this->psr17->createServerRequest('GET', "/api/clients/{$id}")
            ->withAttribute('nene2.auth.claims', ['org' => 1, 'scopes' => ['read:invoices']])
            ->withAttribute(Router::PARAMETERS_ATTRIBUTE, ['id' => (string) $id]);

        $response = $this->handler->handle($request);
        self::assertSame(200, $response->getStatusCode());

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        // `client_id` is the canonical identifier the upstream contract / NeNe Clear
        // reads (C2); `id` is retained as a deprecated alias.
        self::assertSame($id, $body['client_id']);
        self::assertSame($id, $body['id']);
        self::assertSame('株式会社テスト', $body['name']);
        self::assertSame('山田太郎', $body['contact_name']);
        self::assertSame('yamada@example.com', $body['recipient_email']);
        self::assertSame('東京都渋谷区1-1-1', $body['billing_address']);
        self::assertSame('T1234567890123', $body['registration_number']);
    }

    public function test_returns_client_not_found_for_cross_org_client(): void
    {
        $id = $this->clients->save(new Client(organizationId: 2, name: '他社'));

        $request = $this->psr17->createServerRequest('GET', "/api/clients/{$id}")
            ->withAttribute('nene2.auth.claims', ['org' => 1, 'scopes' => ['read:invoices']])
            ->withAttribute(Router::PARAMETERS_ATTRIBUTE, ['id' => (string) $id]);

        $response = $this->handler->handle($request);
        self::assertSame(404, $response->getStatusCode());

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertStringContainsString('client-not-found', (string) ($body['type'] ?? ''));
    }

    public function test_returns_client_not_found_for_nonexistent_client(): void
    {
        $request = $this->psr17->createServerRequest('GET', '/api/clients/999')
            ->withAttribute('nene2.auth.claims', ['org' => 1, 'scopes' => ['read:invoices']])
            ->withAttribute(Router::PARAMETERS_ATTRIBUTE, ['id' => '999']);

        $response = $this->handler->handle($request);
        self::assertSame(404, $response->getStatusCode());

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertStringContainsString('client-not-found', (string) ($body['type'] ?? ''));
    }

    public function test_returns_403_when_no_org_in_token(): void
    {
        $request = $this->psr17->createServerRequest('GET', '/api/clients/1')
            ->withAttribute('nene2.auth.claims', ['scopes' => ['read:invoices']]);

        $response = $this->handler->handle($request);
        self::assertSame(403, $response->getStatusCode());
    }
}
