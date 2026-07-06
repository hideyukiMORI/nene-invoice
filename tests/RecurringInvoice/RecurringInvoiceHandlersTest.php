<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\RecurringInvoice;

use Nene2\Http\JsonResponseFactory;
use Nene2\Http\RequestScopedHolder;
use Nene2\Routing\Router;
use NeneInvoice\Client\Client;
use NeneInvoice\LineItem\TaxCalculator;
use NeneInvoice\RecurringInvoice\CreateRecurringInvoiceHandler;
use NeneInvoice\RecurringInvoice\CreateRecurringInvoiceUseCase;
use NeneInvoice\RecurringInvoice\DeleteRecurringInvoiceHandler;
use NeneInvoice\RecurringInvoice\DeleteRecurringInvoiceUseCase;
use NeneInvoice\RecurringInvoice\GetRecurringInvoiceByIdUseCase;
use NeneInvoice\RecurringInvoice\GetRecurringInvoiceHandler;
use NeneInvoice\RecurringInvoice\ListRecurringInvoicesHandler;
use NeneInvoice\RecurringInvoice\ListRecurringInvoicesUseCase;
use NeneInvoice\RecurringInvoice\RecurringInvoiceNotFoundException;
use NeneInvoice\RecurringInvoice\RecurringInvoiceValidationException;
use NeneInvoice\RecurringInvoice\UpdateRecurringInvoiceHandler;
use NeneInvoice\RecurringInvoice\UpdateRecurringInvoiceUseCase;
use NeneInvoice\Tests\Support\ImmediateTransactionManager;
use NeneInvoice\Tests\Support\InMemoryClientRepository;
use NeneInvoice\Tests\Support\InMemoryLineItemRepository;
use NeneInvoice\Tests\Support\InMemoryRecurringInvoiceRepository;
use NeneInvoice\Tests\Support\RecordingAuditRecorder;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

/**
 * HTTP-layer coverage for the recurring-invoice CRUD handlers (#503), wiring the
 * real use cases against in-memory repositories. Domain exceptions propagate to
 * the error middleware (mapped to 422 / 404 there), so tests assert on them.
 */
final class RecurringInvoiceHandlersTest extends TestCase
{
    private Psr17Factory $psr17;
    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $holder;
    private InMemoryRecurringInvoiceRepository $recurring;
    private InMemoryLineItemRepository $lineItems;
    private InMemoryClientRepository $clients;
    private RecordingAuditRecorder $audit;
    private int $clientId;

    protected function setUp(): void
    {
        $this->psr17 = new Psr17Factory();
        $this->holder = new RequestScopedHolder();
        $this->holder->set(1);
        $this->recurring = new InMemoryRecurringInvoiceRepository($this->holder);
        $this->lineItems = new InMemoryLineItemRepository();
        $this->clients = new InMemoryClientRepository($this->holder);
        $this->audit = new RecordingAuditRecorder();
        $this->clientId = $this->clients->save(new Client(organizationId: 1, name: '顧問先'));
    }

    public function test_list_returns_pagination_envelope(): void
    {
        $this->seed('A');
        $this->seed('B');

        $response = $this->listHandler()->handle($this->get('/admin/recurring-invoices'));

        self::assertSame(200, $response->getStatusCode());
        $body = $this->decode($response->getBody());
        self::assertSame(2, $body['total']);
        self::assertSame(20, $body['limit']);
        self::assertSame(0, $body['offset']);
        self::assertIsArray($body['items']);
        self::assertCount(2, $body['items']);
        // List omits the line template and client name.
        self::assertArrayNotHasKey('line_items', $body['items'][0]);
        self::assertNull($body['items'][0]['client_name']);
    }

    public function test_create_returns_201_and_persists(): void
    {
        $response = $this->createHandler()->handle($this->post('/admin/recurring-invoices', [
            'client_id' => $this->clientId,
            'name' => '月次顧問料',
            'frequency' => 'monthly',
            'first_run_on' => '2026-07-01',
            'line_items' => [
                ['description' => 'コンサル', 'quantity' => 1, 'unit_price_cents' => 10000, 'tax_rate_bps' => 1000],
            ],
        ]));

        self::assertSame(201, $response->getStatusCode());
        $body = $this->decode($response->getBody());
        self::assertSame('月次顧問料', $body['name']);
        self::assertSame('monthly', $body['frequency']);
        self::assertSame(10000, $body['subtotal_cents']);
        self::assertSame(1000, $body['tax_cents']);
        self::assertCount(1, $body['line_items']);

        $id = $body['id'];
        self::assertIsInt($id);
        self::assertNotNull($this->recurring->findById($id));
    }

    public function test_create_invalid_frequency_is_rejected(): void
    {
        $this->expectException(RecurringInvoiceValidationException::class);
        $this->createHandler()->handle($this->post('/admin/recurring-invoices', [
            'client_id' => $this->clientId,
            'name' => '月次顧問料',
            'frequency' => 'weekly',
            'first_run_on' => '2026-07-01',
            'line_items' => [
                ['description' => 'コンサル', 'quantity' => 1, 'unit_price_cents' => 10000, 'tax_rate_bps' => 1000],
            ],
        ]));
    }

    public function test_get_returns_schedule_with_lines(): void
    {
        $id = $this->seed();

        $response = $this->getHandler()->handle($this->get('/admin/recurring-invoices/' . $id, ['id' => (string) $id]));

        self::assertSame(200, $response->getStatusCode());
        $body = $this->decode($response->getBody());
        self::assertSame($id, $body['id']);
        self::assertCount(1, $body['line_items']);
    }

    public function test_get_unknown_id_throws_not_found(): void
    {
        $this->expectException(RecurringInvoiceNotFoundException::class);
        $this->getHandler()->handle($this->get('/admin/recurring-invoices/999', ['id' => '999']));
    }

    public function test_update_returns_200_and_changes(): void
    {
        $id = $this->seed();

        $response = $this->updateHandler()->handle($this->patch('/admin/recurring-invoices/' . $id, ['id' => (string) $id], [
            'client_id' => $this->clientId,
            'name' => '改定 顧問料',
            'frequency' => 'quarterly',
            'next_run_on' => '2026-09-01',
            'is_active' => false,
            'line_items' => [
                ['description' => 'コンサル', 'quantity' => 2, 'unit_price_cents' => 10000, 'tax_rate_bps' => 1000],
            ],
        ]));

        self::assertSame(200, $response->getStatusCode());
        $body = $this->decode($response->getBody());
        self::assertSame('改定 顧問料', $body['name']);
        self::assertSame('quarterly', $body['frequency']);
        self::assertSame('2026-09-01', $body['next_run_on']);
        self::assertFalse($body['is_active']);
        self::assertSame(20000, $body['subtotal_cents']);
    }

    public function test_update_unknown_id_throws_not_found(): void
    {
        $this->expectException(RecurringInvoiceNotFoundException::class);
        $this->updateHandler()->handle($this->patch('/admin/recurring-invoices/999', ['id' => '999'], [
            'client_id' => $this->clientId,
            'name' => 'x',
            'frequency' => 'monthly',
            'next_run_on' => '2026-07-01',
            'line_items' => [
                ['description' => 'x', 'quantity' => 1, 'unit_price_cents' => 1000, 'tax_rate_bps' => 1000],
            ],
        ]));
    }

    public function test_delete_returns_204_and_soft_deletes(): void
    {
        $id = $this->seed();

        $response = $this->deleteHandler()->handle($this->delete('/admin/recurring-invoices/' . $id, ['id' => (string) $id]));

        self::assertSame(204, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody());
        self::assertNull($this->recurring->findById($id));
    }

    public function test_delete_unknown_id_throws_not_found(): void
    {
        $this->expectException(RecurringInvoiceNotFoundException::class);
        $this->deleteHandler()->handle($this->delete('/admin/recurring-invoices/999', ['id' => '999']));
    }

    private function seed(string $name = '月次顧問料'): int
    {
        $response = $this->createHandler()->handle($this->post('/admin/recurring-invoices', [
            'client_id' => $this->clientId,
            'name' => $name,
            'frequency' => 'monthly',
            'first_run_on' => '2026-07-01',
            'line_items' => [
                ['description' => 'コンサル', 'quantity' => 1, 'unit_price_cents' => 10000, 'tax_rate_bps' => 1000],
            ],
        ]));

        $id = $this->decode($response->getBody())['id'];
        self::assertIsInt($id);

        return $id;
    }

    private function listHandler(): ListRecurringInvoicesHandler
    {
        return new ListRecurringInvoicesHandler(new ListRecurringInvoicesUseCase($this->recurring), $this->json());
    }

    private function getHandler(): GetRecurringInvoiceHandler
    {
        return new GetRecurringInvoiceHandler(new GetRecurringInvoiceByIdUseCase($this->recurring, $this->lineItems), $this->json());
    }

    private function createHandler(): CreateRecurringInvoiceHandler
    {
        $useCase = new CreateRecurringInvoiceUseCase(
            new ImmediateTransactionManager(),
            fn () => $this->recurring,
            fn () => $this->lineItems,
            $this->clients,
            new TaxCalculator(),
            $this->audit,
            $this->holder,
        );

        return new CreateRecurringInvoiceHandler($useCase, $this->json());
    }

    private function updateHandler(): UpdateRecurringInvoiceHandler
    {
        $useCase = new UpdateRecurringInvoiceUseCase(
            $this->recurring,
            $this->lineItems,
            new ImmediateTransactionManager(),
            fn () => $this->recurring,
            fn () => $this->lineItems,
            $this->clients,
            new TaxCalculator(),
            $this->audit,
            $this->holder,
        );

        return new UpdateRecurringInvoiceHandler($useCase, $this->json());
    }

    private function deleteHandler(): DeleteRecurringInvoiceHandler
    {
        $useCase = new DeleteRecurringInvoiceUseCase(
            $this->recurring,
            $this->lineItems,
            new ImmediateTransactionManager(),
            fn () => $this->recurring,
            $this->audit,
            $this->holder,
        );

        return new DeleteRecurringInvoiceHandler($useCase, $this->json());
    }

    private function json(): JsonResponseFactory
    {
        return new JsonResponseFactory($this->psr17, $this->psr17);
    }

    /** @param array<string, string> $params */
    private function get(string $path, array $params = []): ServerRequestInterface
    {
        return $this->base('GET', $path, $params);
    }

    /**
     * @param array<string, string> $params
     * @param array<string, mixed> $body
     */
    private function post(string $path, array $body, array $params = []): ServerRequestInterface
    {
        return $this->base('POST', $path, $params)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psr17->createStream((string) json_encode($body)));
    }

    /**
     * @param array<string, string> $params
     * @param array<string, mixed> $body
     */
    private function patch(string $path, array $params, array $body): ServerRequestInterface
    {
        return $this->base('PATCH', $path, $params)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psr17->createStream((string) json_encode($body)));
    }

    /** @param array<string, string> $params */
    private function delete(string $path, array $params): ServerRequestInterface
    {
        return $this->base('DELETE', $path, $params);
    }

    /** @param array<string, string> $params */
    private function base(string $method, string $path, array $params): ServerRequestInterface
    {
        $request = $this->psr17->createServerRequest($method, $path)
            ->withAttribute('nene2.auth.claims', ['sub' => 7, 'org' => 1, 'role' => 'admin']);

        if ($params !== []) {
            $request = $request->withAttribute(Router::PARAMETERS_ATTRIBUTE, $params);
        }

        return $request;
    }

    /** @return array<string, mixed> */
    private function decode(\Psr\Http\Message\StreamInterface $body): array
    {
        $decoded = json_decode((string) $body, true);
        self::assertIsArray($decoded);

        return $decoded;
    }
}
