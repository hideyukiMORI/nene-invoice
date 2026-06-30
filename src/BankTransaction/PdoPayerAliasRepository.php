<?php

declare(strict_types=1);

namespace NeneInvoice\BankTransaction;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Http\RequestScopedHolder;

final readonly class PdoPayerAliasRepository implements PayerAliasRepositoryInterface
{
    private const COLUMNS = 'id, organization_id, normalized_name, client_id, created_at, updated_at';

    /**
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private DatabaseQueryExecutorInterface $query,
        private RequestScopedHolder $orgId,
    ) {
    }

    public function upsert(PayerAlias $alias): int
    {
        $existing = $this->findByNormalizedName($alias->normalizedName);
        $now      = date('Y-m-d H:i:s');

        if ($existing !== null && $existing->id !== null) {
            $this->query->execute(
                'UPDATE payer_aliases SET client_id = ?, updated_at = ? WHERE id = ? AND organization_id = ?',
                [$alias->clientId, $now, $existing->id, $this->orgId->get()],
            );

            return $existing->id;
        }

        // The organization is forced from the request-scoped holder.
        $this->query->execute(
            'INSERT INTO payer_aliases (organization_id, normalized_name, client_id, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?)',
            [$this->orgId->get(), $alias->normalizedName, $alias->clientId, $now, $now],
        );

        return $this->query->lastInsertId();
    }

    public function findById(int $id): ?PayerAlias
    {
        $row = $this->query->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM payer_aliases WHERE id = ? AND organization_id = ?',
            [$id, $this->orgId->get()],
        );

        return $row !== null ? $this->mapRow($row) : null;
    }

    public function findByNormalizedName(string $normalizedName): ?PayerAlias
    {
        $row = $this->query->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM payer_aliases WHERE organization_id = ? AND normalized_name = ?',
            [$this->orgId->get(), $normalizedName],
        );

        return $row !== null ? $this->mapRow($row) : null;
    }

    /** @return list<PayerAlias> */
    public function findByOrganization(int $limit, int $offset): array
    {
        $rows = $this->query->fetchAll(
            'SELECT ' . self::COLUMNS . ' FROM payer_aliases
             WHERE organization_id = ?
             ORDER BY normalized_name ASC LIMIT ? OFFSET ?',
            [$this->orgId->get(), $limit, $offset],
        );

        return array_map(fn (array $row): PayerAlias => $this->mapRow($row), $rows);
    }

    public function countByOrganization(): int
    {
        $row = $this->query->fetchOne(
            'SELECT COUNT(*) AS cnt FROM payer_aliases WHERE organization_id = ?',
            [$this->orgId->get()],
        );

        return $row !== null ? (int) $row['cnt'] : 0;
    }

    public function delete(int $id): void
    {
        if ($this->findById($id) === null) {
            throw new PayerAliasNotFoundException($id);
        }

        $this->query->execute(
            'DELETE FROM payer_aliases WHERE id = ? AND organization_id = ?',
            [$id, $this->orgId->get()],
        );
    }

    /** @param array<string, mixed> $row */
    private function mapRow(array $row): PayerAlias
    {
        return new PayerAlias(
            organizationId: (int) $row['organization_id'],
            normalizedName: (string) $row['normalized_name'],
            clientId: (int) $row['client_id'],
            id: (int) $row['id'],
            createdAt: (string) $row['created_at'],
            updatedAt: (string) $row['updated_at'],
        );
    }
}
