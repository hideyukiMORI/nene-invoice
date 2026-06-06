<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Client;

use Nene2\Config\DatabaseConfig;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Client\Client;
use NeneInvoice\Client\ClientListFilter;
use NeneInvoice\Client\ClientNotFoundException;
use NeneInvoice\Client\ClientSort;
use NeneInvoice\Client\PdoClientRepository;
use PHPUnit\Framework\TestCase;

final class PdoClientRepositoryTest extends TestCase
{
    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $orgId;
    private PdoClientRepository $repository;

    protected function setUp(): void
    {
        $config = new DatabaseConfig(
            url: null,
            environment: 'test',
            adapter: 'sqlite',
            host: 'localhost',
            port: 1,
            name: ':memory:',
            user: 'sqlite',
            password: '',
            charset: 'utf8',
        );
        $factory = new PdoConnectionFactory($config);
        $pdo = $factory->create();

        $schema = file_get_contents(dirname(__DIR__, 2) . '/database/schema/clients.sql');
        self::assertIsString($schema);
        $pdo->exec($schema);

        $this->orgId = new RequestScopedHolder();
        $this->orgId->set(1);
        $this->repository = new PdoClientRepository(new PdoDatabaseQueryExecutor($factory, $pdo), $this->orgId);
    }

    public function test_saves_and_reads_back_a_client(): void
    {
        $id = $this->repository->save(new Client(
            organizationId: 1,
            name: 'Acme Foods',
            contactName: 'Taro',
            email: 'taro@acme.test',
            registrationNumber: 'T1234567890123',
        ));

        $client = $this->repository->findById($id);
        self::assertNotNull($client);
        self::assertSame('Acme Foods', $client->name);
        self::assertSame('Taro', $client->contactName);
        self::assertSame('T1234567890123', $client->registrationNumber);
        self::assertFalse($client->isDeleted);
    }

    public function test_list_and_count_are_scoped_to_organization(): void
    {
        $this->repository->save(new Client(organizationId: 1, name: 'A'));
        $this->repository->save(new Client(organizationId: 1, name: 'B'));

        $this->orgId->set(2);
        $this->repository->save(new Client(organizationId: 2, name: 'C'));

        $this->orgId->set(1);
        self::assertSame(2, $this->repository->countForAdminList(new ClientListFilter()));
        self::assertCount(2, $this->repository->findForAdminList(new ClientListFilter(), new ClientSort(), 10, 0));

        $this->orgId->set(2);
        self::assertSame(1, $this->repository->countForAdminList(new ClientListFilter()));
    }

    public function test_find_by_id_is_scoped_to_organization(): void
    {
        $id = $this->repository->save(new Client(organizationId: 1, name: 'Acme'));

        // A caller in another org must not read the row even by direct id.
        $this->orgId->set(2);
        self::assertNull($this->repository->findById($id));

        $this->orgId->set(1);
        self::assertNotNull($this->repository->findById($id));
    }

    public function test_soft_delete_hides_client_from_reads(): void
    {
        $id = $this->repository->save(new Client(organizationId: 1, name: 'Temp'));

        $this->repository->delete($id);

        self::assertNull($this->repository->findById($id));
        self::assertSame(0, $this->repository->countForAdminList(new ClientListFilter()));
        self::assertCount(0, $this->repository->findForAdminList(new ClientListFilter(), new ClientSort(), 10, 0));
    }

    public function test_delete_throws_for_unknown_client(): void
    {
        $this->expectException(ClientNotFoundException::class);
        $this->repository->delete(4242);
    }
}
