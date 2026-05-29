<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Client;

use NeneInvoice\Client\Client;
use NeneInvoice\Client\ClientNotFoundException;
use NeneInvoice\Client\CreateClientInput;
use NeneInvoice\Client\CreateClientUseCase;
use NeneInvoice\Client\DeleteClientUseCase;
use NeneInvoice\Client\InvalidRegistrationNumberException;
use NeneInvoice\Client\UpdateClientInput;
use NeneInvoice\Client\UpdateClientUseCase;
use NeneInvoice\Tests\Support\InMemoryClientRepository;
use PHPUnit\Framework\TestCase;

final class ClientWriteUseCasesTest extends TestCase
{
    private InMemoryClientRepository $repo;

    protected function setUp(): void
    {
        $this->repo = new InMemoryClientRepository();
    }

    public function test_create_forces_caller_organization(): void
    {
        $client = (new CreateClientUseCase($this->repo))->execute(7, new CreateClientInput(name: 'Acme'));

        self::assertSame(7, $client->organizationId);
        self::assertSame('Acme', $client->name);
    }

    public function test_create_accepts_valid_registration_number(): void
    {
        $client = (new CreateClientUseCase($this->repo))->execute(1, new CreateClientInput(name: 'Acme', registrationNumber: 'T1234567890123'));

        self::assertSame('T1234567890123', $client->registrationNumber);
    }

    public function test_create_rejects_malformed_registration_number(): void
    {
        $this->expectException(InvalidRegistrationNumberException::class);
        (new CreateClientUseCase($this->repo))->execute(1, new CreateClientInput(name: 'Acme', registrationNumber: '12345'));
    }

    public function test_update_blocks_cross_organization_target(): void
    {
        $otherOrg = $this->repo->save(new Client(organizationId: 2, name: 'Other'));

        $this->expectException(ClientNotFoundException::class);
        (new UpdateClientUseCase($this->repo))->execute(1, $otherOrg, new UpdateClientInput(name: 'Hacked'));
    }

    public function test_update_changes_fields_in_same_organization(): void
    {
        $id = $this->repo->save(new Client(organizationId: 1, name: 'Before'));

        $updated = (new UpdateClientUseCase($this->repo))->execute(1, $id, new UpdateClientInput(name: 'After', email: 'a@x'));

        self::assertSame('After', $updated->name);
        self::assertSame('a@x', $updated->email);
    }

    public function test_delete_soft_deletes_in_same_organization(): void
    {
        $id = $this->repo->save(new Client(organizationId: 1, name: 'Temp'));

        (new DeleteClientUseCase($this->repo))->execute(1, $id);

        self::assertNull($this->repo->findById($id));
    }

    public function test_delete_blocks_cross_organization_target(): void
    {
        $otherOrg = $this->repo->save(new Client(organizationId: 2, name: 'Other'));

        $this->expectException(ClientNotFoundException::class);
        (new DeleteClientUseCase($this->repo))->execute(1, $otherOrg);
    }
}
