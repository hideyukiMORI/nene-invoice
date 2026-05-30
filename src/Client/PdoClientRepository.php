<?php

declare(strict_types=1);

namespace NeneInvoice\Client;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Http\RequestScopedHolder;

final readonly class PdoClientRepository implements ClientRepositoryInterface
{
    private const COLUMNS = 'id, organization_id, name, contact_name, email, billing_address, registration_number, is_deleted, created_at, updated_at';

    /**
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private DatabaseQueryExecutorInterface $query,
        private RequestScopedHolder $orgId,
    ) {
    }

    public function findById(int $id): ?Client
    {
        $row = $this->query->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM clients WHERE id = ? AND organization_id = ? AND is_deleted = 0',
            [$id, $this->orgId->get()],
        );

        return $row !== null ? $this->mapRow($row) : null;
    }

    /** @return list<Client> */
    public function findAll(int $limit, int $offset): array
    {
        $rows = $this->query->fetchAll(
            'SELECT ' . self::COLUMNS . ' FROM clients WHERE organization_id = ? AND is_deleted = 0 ORDER BY id ASC LIMIT ? OFFSET ?',
            [$this->orgId->get(), $limit, $offset],
        );

        return array_map(fn (array $row): Client => $this->mapRow($row), $rows);
    }

    public function count(): int
    {
        $row = $this->query->fetchOne(
            'SELECT COUNT(*) AS cnt FROM clients WHERE organization_id = ? AND is_deleted = 0',
            [$this->orgId->get()],
        );

        return $row !== null ? (int) $row['cnt'] : 0;
    }

    public function save(Client $client): int
    {
        $now = date('Y-m-d H:i:s');

        // The organization is forced from the request-scoped holder, never from
        // the entity — a write always lands in the caller's resolved org.
        $this->query->execute(
            'INSERT INTO clients (organization_id, name, contact_name, email, billing_address, registration_number, is_deleted, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?)',
            [
                $this->orgId->get(),
                $client->name,
                $client->contactName,
                $client->email,
                $client->billingAddress,
                $client->registrationNumber,
                $now,
                $now,
            ],
        );

        return $this->query->lastInsertId();
    }

    public function update(Client $client): void
    {
        if ($client->id === null) {
            throw new ClientNotFoundException(0);
        }

        $now = date('Y-m-d H:i:s');

        $affected = $this->query->execute(
            'UPDATE clients SET name = ?, contact_name = ?, email = ?, billing_address = ?, registration_number = ?, updated_at = ? WHERE id = ? AND organization_id = ? AND is_deleted = 0',
            [
                $client->name,
                $client->contactName,
                $client->email,
                $client->billingAddress,
                $client->registrationNumber,
                $now,
                $client->id,
                $this->orgId->get(),
            ],
        );

        if ($affected === 0 && $this->findById($client->id) === null) {
            throw new ClientNotFoundException($client->id);
        }
    }

    public function delete(int $id): void
    {
        if ($this->findById($id) === null) {
            throw new ClientNotFoundException($id);
        }

        $this->query->execute(
            'UPDATE clients SET is_deleted = 1, deleted_at = ? WHERE id = ? AND organization_id = ?',
            [date('Y-m-d H:i:s'), $id, $this->orgId->get()],
        );
    }

    /** @param array<string, mixed> $row */
    private function mapRow(array $row): Client
    {
        return new Client(
            organizationId: (int) $row['organization_id'],
            name: (string) $row['name'],
            contactName: isset($row['contact_name']) && $row['contact_name'] !== '' ? (string) $row['contact_name'] : null,
            email: isset($row['email']) && $row['email'] !== '' ? (string) $row['email'] : null,
            billingAddress: isset($row['billing_address']) && $row['billing_address'] !== '' ? (string) $row['billing_address'] : null,
            registrationNumber: isset($row['registration_number']) && $row['registration_number'] !== '' ? (string) $row['registration_number'] : null,
            isDeleted: (bool) $row['is_deleted'],
            id: (int) $row['id'],
            createdAt: (string) $row['created_at'],
            updatedAt: (string) $row['updated_at'],
        );
    }
}
