<?php

declare(strict_types=1);

namespace NeneInvoice\User;

use Nene2\Database\DatabaseConstraintException;
use Nene2\Database\DatabaseQueryExecutorInterface;
use NeneInvoice\Auth\Role;

final readonly class PdoUserRepository implements UserRepositoryInterface
{
    private const COLUMNS = 'id, email, password_hash, role, organization_id, status, created_at, updated_at';

    public function __construct(
        private DatabaseQueryExecutorInterface $query,
    ) {
    }

    public function findById(int $id): ?User
    {
        $row = $this->query->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM users WHERE id = ?',
            [$id],
        );

        return $row !== null ? $this->mapRow($row) : null;
    }

    public function findByEmail(string $email): ?User
    {
        $row = $this->query->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM users WHERE email = ?',
            [$email],
        );

        return $row !== null ? $this->mapRow($row) : null;
    }

    /** @return list<User> */
    public function findAllByOrganization(int $organizationId, int $limit, int $offset): array
    {
        $rows = $this->query->fetchAll(
            'SELECT ' . self::COLUMNS . ' FROM users WHERE organization_id = ? ORDER BY id ASC LIMIT ? OFFSET ?',
            [$organizationId, $limit, $offset],
        );

        return array_map(fn (array $row): User => $this->mapRow($row), $rows);
    }

    public function countByOrganization(int $organizationId): int
    {
        $row = $this->query->fetchOne(
            'SELECT COUNT(*) AS cnt FROM users WHERE organization_id = ?',
            [$organizationId],
        );

        return $row !== null ? (int) $row['cnt'] : 0;
    }

    public function save(User $user): int
    {
        $now = date('Y-m-d H:i:s');

        try {
            $this->query->execute(
                'INSERT INTO users (email, password_hash, role, organization_id, status, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [
                    $user->email,
                    $user->passwordHash,
                    $user->role->value,
                    $user->organizationId,
                    $user->status,
                    $now,
                    $now,
                ],
            );
        } catch (DatabaseConstraintException $e) {
            throw new UserEmailConflictException($user->email, $e);
        }

        return $this->query->lastInsertId();
    }

    public function update(User $user): void
    {
        if ($user->id === null) {
            throw new UserNotFoundException(0);
        }

        $now = date('Y-m-d H:i:s');

        $affected = $this->query->execute(
            'UPDATE users SET email = ?, password_hash = ?, role = ?, organization_id = ?, status = ?, updated_at = ? WHERE id = ?',
            [
                $user->email,
                $user->passwordHash,
                $user->role->value,
                $user->organizationId,
                $user->status,
                $now,
                $user->id,
            ],
        );

        if ($affected === 0 && $this->findById($user->id) === null) {
            throw new UserNotFoundException($user->id);
        }
    }

    public function delete(int $id): void
    {
        if ($this->findById($id) === null) {
            throw new UserNotFoundException($id);
        }

        $this->query->execute('DELETE FROM users WHERE id = ?', [$id]);
    }

    /** @param array<string, mixed> $row */
    private function mapRow(array $row): User
    {
        return new User(
            email: (string) $row['email'],
            passwordHash: (string) $row['password_hash'],
            role: Role::from((string) $row['role']),
            organizationId: isset($row['organization_id']) ? (int) $row['organization_id'] : null,
            status: (string) $row['status'],
            id: (int) $row['id'],
            createdAt: (string) $row['created_at'],
            updatedAt: (string) $row['updated_at'],
        );
    }
}
