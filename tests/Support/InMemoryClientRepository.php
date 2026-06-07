<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Support;

use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Client\Client;
use NeneInvoice\Client\ClientListFilter;
use NeneInvoice\Client\ClientNotFoundException;
use NeneInvoice\Client\ClientRepositoryInterface;
use NeneInvoice\Client\ClientSort;

/**
 * In-memory fake for use-case tests (no database). Reads are scoped to the
 * request-scoped org holder, mirroring {@see \NeneInvoice\Client\PdoClientRepository}.
 * `save` keeps the entity's org so tests can seed cross-tenant fixtures; reads
 * then prove the holder-based isolation. Soft-deleted clients are excluded.
 *
 * The holder defaults to organization 1 so existing single-org tests keep
 * working without wiring; pass a preset holder to test other organizations.
 */
final class InMemoryClientRepository implements ClientRepositoryInterface
{
    /** @var array<int, Client> */
    private array $byId = [];
    private int $nextId = 1;

    /** @var RequestScopedHolder<int> */
    private RequestScopedHolder $orgId;

    /** @param RequestScopedHolder<int>|null $orgId */
    public function __construct(?RequestScopedHolder $orgId = null)
    {
        if ($orgId === null) {
            $orgId = new RequestScopedHolder();
            $orgId->set(1);
        }

        $this->orgId = $orgId;
    }

    public function findById(int $id): ?Client
    {
        $client = $this->byId[$id] ?? null;

        return $client !== null && !$client->isDeleted && $client->organizationId === $this->orgId->get()
            ? $client
            : null;
    }

    /** @return list<Client> */
    public function findForAdminList(ClientListFilter $filter, ClientSort $sort, int $limit, int $offset): array
    {
        $matches = $this->adminFiltered($filter);

        usort($matches, static function (Client $a, Client $b) use ($sort): int {
            $cmp = match ($sort->field) {
                'contact'      => strcmp((string) $a->contactName, (string) $b->contactName),
                'email'        => strcmp((string) $a->email, (string) $b->email),
                'registration' => strcmp((string) $a->registrationNumber, (string) $b->registrationNumber),
                default        => strcmp($a->name, $b->name),
            };

            return $sort->descending ? -$cmp : $cmp;
        });

        return array_slice($matches, $offset, $limit);
    }

    public function countForAdminList(ClientListFilter $filter): int
    {
        return count($this->adminFiltered($filter));
    }

    /** @return list<Client> */
    private function adminFiltered(ClientListFilter $filter): array
    {
        $orgId  = $this->orgId->get();
        $search = $filter->search;

        return array_values(array_filter($this->byId, static function (Client $c) use ($orgId, $search): bool {
            if ($c->organizationId !== $orgId || $c->isDeleted) {
                return false;
            }
            if ($search === null) {
                return true;
            }
            $haystack = $c->name . ' ' . ($c->contactName ?? '') . ' ' . ($c->email ?? '')
                . ' ' . ($c->registrationNumber ?? '');

            return stripos($haystack, $search) !== false;
        }));
    }

    /** @return list<Client> */
    public function findForExport(ClientListFilter $filter): array
    {
        $matches = $this->adminFiltered($filter);

        usort($matches, static fn (Client $a, Client $b): int => strcmp($a->name, $b->name));

        return $matches;
    }

    public function save(Client $client): int
    {
        $id = $this->nextId++;
        $this->byId[$id] = new Client(
            organizationId: $client->organizationId,
            name: $client->name,
            contactName: $client->contactName,
            email: $client->email,
            billingAddress: $client->billingAddress,
            registrationNumber: $client->registrationNumber,
            isDeleted: false,
            id: $id,
            createdAt: '2026-05-29 00:00:00',
            updatedAt: '2026-05-29 00:00:00',
        );

        return $id;
    }

    public function update(Client $client): void
    {
        if ($client->id === null || $this->findById($client->id) === null) {
            throw new ClientNotFoundException($client->id ?? 0);
        }

        $this->byId[$client->id] = $client;
    }

    public function delete(int $id): void
    {
        $existing = $this->findById($id);

        if ($existing === null) {
            throw new ClientNotFoundException($id);
        }

        $this->byId[$id] = new Client(
            organizationId: $existing->organizationId,
            name: $existing->name,
            contactName: $existing->contactName,
            email: $existing->email,
            billingAddress: $existing->billingAddress,
            registrationNumber: $existing->registrationNumber,
            isDeleted: true,
            id: $existing->id,
            createdAt: $existing->createdAt,
            updatedAt: $existing->updatedAt,
        );
    }
}
