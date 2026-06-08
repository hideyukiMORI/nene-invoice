<?php

declare(strict_types=1);

namespace NeneInvoice\Client;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Support\SqlLike;

final readonly class PdoClientRepository implements ClientRepositoryInterface
{
    private const COLUMNS = 'id, organization_id, name, name_kana, contact_name, email, billing_address, registration_number, is_deleted, created_at, updated_at';

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
            'SELECT ' . self::COLUMNS . ' FROM clients WHERE id = ? AND organization_id = ? AND is_deleted = FALSE',
            [$id, $this->orgId->get()],
        );

        return $row !== null ? $this->mapRow($row) : null;
    }

    /**
     * Admin list: searched + sorted.
     *
     * @return list<Client>
     */
    public function findForAdminList(ClientListFilter $filter, ClientSort $sort, int $limit, int $offset): array
    {
        [$where, $params] = $this->buildAdminWhere($filter);

        $rows = $this->query->fetchAll(
            'SELECT ' . self::COLUMNS . ' FROM clients WHERE ' . $where
                . ' ORDER BY ' . self::orderByClause($sort) . ' LIMIT ? OFFSET ?',
            [...$params, $limit, $offset],
        );

        return array_map(fn (array $row): Client => $this->mapRow($row), $rows);
    }

    public function countForAdminList(ClientListFilter $filter): int
    {
        [$where, $params] = $this->buildAdminWhere($filter);

        $row = $this->query->fetchOne('SELECT COUNT(*) AS cnt FROM clients WHERE ' . $where, $params);

        return $row !== null ? (int) $row['cnt'] : 0;
    }

    /**
     * @return array{0: string, 1: list<int|string>}
     */
    private function buildAdminWhere(ClientListFilter $filter): array
    {
        $clauses = ['organization_id = ?', 'is_deleted = FALSE'];
        /** @var list<int|string> $params */
        $params = [$this->orgId->get()];

        if ($filter->search !== null) {
            $clauses[] = "(LOWER(name) LIKE LOWER(?) ESCAPE '!' OR LOWER(name_kana) LIKE LOWER(?) ESCAPE '!'"
                . " OR LOWER(contact_name) LIKE LOWER(?) ESCAPE '!'"
                . " OR LOWER(email) LIKE LOWER(?) ESCAPE '!' OR LOWER(registration_number) LIKE LOWER(?) ESCAPE '!')";
            $like = '%' . SqlLike::escape($filter->search) . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        return [implode(' AND ', $clauses), $params];
    }

    public function findForExport(ClientListFilter $filter): array
    {
        [$where, $params] = $this->buildAdminWhere($filter);

        $rows = $this->query->fetchAll(
            'SELECT ' . self::COLUMNS . ' FROM clients WHERE ' . $where . ' ORDER BY name ASC, id ASC',
            $params,
        );

        return array_map(fn (array $row): Client => $this->mapRow($row), $rows);
    }

    /** Maps a whitelisted sort field to a SQL ORDER BY, with `id` as tiebreak. */
    private static function orderByClause(ClientSort $sort): string
    {
        $columns = [
            'name'         => 'name',
            'contact'      => 'contact_name',
            'email'        => 'email',
            'registration' => 'registration_number',
        ];

        $direction = $sort->descending ? 'DESC' : 'ASC';

        if ($sort->field === null || !isset($columns[$sort->field])) {
            return 'name ' . $direction . ', id ASC';
        }

        return $columns[$sort->field] . ' ' . $direction . ', id ASC';
    }

    public function save(Client $client): int
    {
        $now = date('Y-m-d H:i:s');

        // The organization is forced from the request-scoped holder, never from
        // the entity — a write always lands in the caller's resolved org.
        $this->query->execute(
            'INSERT INTO clients (organization_id, name, name_kana, contact_name, email, billing_address, registration_number, is_deleted, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, FALSE, ?, ?)',
            [
                $this->orgId->get(),
                $client->name,
                $client->nameKana,
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
            'UPDATE clients SET name = ?, name_kana = ?, contact_name = ?, email = ?, billing_address = ?, registration_number = ?, updated_at = ? WHERE id = ? AND organization_id = ? AND is_deleted = FALSE',
            [
                $client->name,
                $client->nameKana,
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
            'UPDATE clients SET is_deleted = TRUE, deleted_at = ? WHERE id = ? AND organization_id = ?',
            [date('Y-m-d H:i:s'), $id, $this->orgId->get()],
        );
    }

    /** @param array<string, mixed> $row */
    private function mapRow(array $row): Client
    {
        return new Client(
            organizationId: (int) $row['organization_id'],
            name: (string) $row['name'],
            nameKana: isset($row['name_kana']) && $row['name_kana'] !== '' ? (string) $row['name_kana'] : null,
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
