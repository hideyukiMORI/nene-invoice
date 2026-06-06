<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Client;

use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Client\Client;
use NeneInvoice\Client\ClientListFilter;
use NeneInvoice\Client\ClientNotFoundException;
use NeneInvoice\Client\ClientSort;
use NeneInvoice\Client\GetClientByIdUseCase;
use NeneInvoice\Client\ListClientsUseCase;
use NeneInvoice\Tests\Support\InMemoryClientRepository;
use PHPUnit\Framework\TestCase;

final class ClientQueryUseCasesTest extends TestCase
{
    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $holder;
    private InMemoryClientRepository $repo;

    protected function setUp(): void
    {
        $this->holder = new RequestScopedHolder();
        $this->holder->set(1);
        $this->repo = new InMemoryClientRepository($this->holder);
        $this->repo->save(new Client(organizationId: 1, name: 'A'));
        $this->repo->save(new Client(organizationId: 1, name: 'B'));
        $this->repo->save(new Client(organizationId: 2, name: 'C'));
    }

    public function test_list_is_scoped_to_organization(): void
    {
        $result = (new ListClientsUseCase($this->repo))->executeAdmin(new ClientListFilter(), new ClientSort(), 10, 0);

        self::assertSame(2, $result->total);
        self::assertCount(2, $result->items);
        foreach ($result->items as $client) {
            self::assertSame(1, $client->organizationId);
        }
    }

    public function test_get_returns_client_in_same_organization(): void
    {
        $client = (new GetClientByIdUseCase($this->repo))->execute(1);

        self::assertSame('A', $client->name);
    }

    public function test_get_hides_client_from_another_organization(): void
    {
        // id 3 belongs to org 2; an org-1 caller must not see it (SQL-scoped).
        $this->expectException(ClientNotFoundException::class);
        (new GetClientByIdUseCase($this->repo))->execute(3);
    }
}
