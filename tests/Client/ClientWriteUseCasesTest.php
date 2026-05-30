<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Client;

use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Client\Client;
use NeneInvoice\Client\ClientNotFoundException;
use NeneInvoice\Client\CreateClientInput;
use NeneInvoice\Client\CreateClientUseCase;
use NeneInvoice\Client\DeleteClientUseCase;
use NeneInvoice\Client\InvalidRegistrationNumberException;
use NeneInvoice\Client\UpdateClientInput;
use NeneInvoice\Client\UpdateClientUseCase;
use NeneInvoice\Tests\Support\InMemoryClientRepository;
use NeneInvoice\Tests\Support\RecordingAuditRecorder;
use PHPUnit\Framework\TestCase;

final class ClientWriteUseCasesTest extends TestCase
{
    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $holder;
    private InMemoryClientRepository $repo;
    private RecordingAuditRecorder $audit;

    protected function setUp(): void
    {
        $this->holder = new RequestScopedHolder();
        $this->holder->set(1);
        $this->repo = new InMemoryClientRepository($this->holder);
        $this->audit = new RecordingAuditRecorder();
    }

    public function test_create_forces_resolved_organization_and_audits(): void
    {
        // The org comes from the request-scoped holder, never from input.
        $this->holder->set(7);

        $client = (new CreateClientUseCase($this->repo, $this->audit, $this->holder))->execute(42, new CreateClientInput(name: 'Acme'));

        self::assertSame(7, $client->organizationId);

        self::assertCount(1, $this->audit->records);
        $record = $this->audit->records[0];
        self::assertSame('client.created', $record['action']);
        self::assertSame(42, $record['actor_user_id']);
        self::assertSame(7, $record['organization_id']);
        self::assertNull($record['before']);
        self::assertIsArray($record['after']);
        self::assertSame('Acme', $record['after']['name']);
    }

    public function test_create_rejects_malformed_registration_number(): void
    {
        $this->expectException(InvalidRegistrationNumberException::class);
        (new CreateClientUseCase($this->repo, $this->audit, $this->holder))->execute(1, new CreateClientInput(name: 'Acme', registrationNumber: '12345'));
    }

    public function test_update_records_before_and_after(): void
    {
        $id = $this->repo->save(new Client(organizationId: 1, name: 'Before'));

        (new UpdateClientUseCase($this->repo, $this->audit, $this->holder))->execute(9, $id, new UpdateClientInput(name: 'After'));

        self::assertCount(1, $this->audit->records);
        $record = $this->audit->records[0];
        self::assertSame('client.updated', $record['action']);
        self::assertSame('Before', $record['before']['name'] ?? null);
        self::assertSame('After', $record['after']['name'] ?? null);
    }

    public function test_update_blocks_cross_organization_target(): void
    {
        $otherOrg = $this->repo->save(new Client(organizationId: 2, name: 'Other'));

        $this->expectException(ClientNotFoundException::class);
        (new UpdateClientUseCase($this->repo, $this->audit, $this->holder))->execute(1, $otherOrg, new UpdateClientInput(name: 'Hacked'));
    }

    public function test_delete_soft_deletes_and_records_before_only(): void
    {
        $id = $this->repo->save(new Client(organizationId: 1, name: 'Temp'));

        (new DeleteClientUseCase($this->repo, $this->audit, $this->holder))->execute(5, $id);

        self::assertNull($this->repo->findById($id));

        $record = $this->audit->records[0];
        self::assertSame('client.deleted', $record['action']);
        self::assertSame('Temp', $record['before']['name'] ?? null);
        self::assertNull($record['after']);
    }

    public function test_delete_blocks_cross_organization_target(): void
    {
        $otherOrg = $this->repo->save(new Client(organizationId: 2, name: 'Other'));

        $this->expectException(ClientNotFoundException::class);
        (new DeleteClientUseCase($this->repo, $this->audit, $this->holder))->execute(1, $otherOrg);
    }
}
