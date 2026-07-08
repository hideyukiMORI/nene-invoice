<?php

declare(strict_types=1);

namespace NeneInvoice\User;

use Nene2\Database\DatabaseConstraintException;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Http\ClockInterface;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Auth\Role;

final readonly class PdoUserRepository implements UserRepositoryInterface
{
    private const COLUMNS = 'id, email, password_hash, role, organization_id, status, created_at, updated_at';

    /**
     * @param RequestScopedHolder<int> $orgId resolved organization for org-scoped operations
     */
    public function __construct(
        private DatabaseQueryExecutorInterface $query,
        private RequestScopedHolder $orgId,
        private ClockInterface $clock,
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

    public function findInOrganization(int $id): ?User
    {
        $row = $this->query->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM users WHERE id = ? AND organization_id = ?',
            [$id, $this->orgId->get()],
        );

        return $row !== null ? $this->mapRow($row) : null;
    }

    /** @return list<User> */
    public function findAll(int $limit, int $offset): array
    {
        $rows = $this->query->fetchAll(
            'SELECT ' . self::COLUMNS . ' FROM users WHERE organization_id = ? ORDER BY id ASC LIMIT ? OFFSET ?',
            [$this->orgId->get(), $limit, $offset],
        );

        return array_map(fn (array $row): User => $this->mapRow($row), $rows);
    }

    public function count(): int
    {
        $row = $this->query->fetchOne(
            'SELECT COUNT(*) AS cnt FROM users WHERE organization_id = ?',
            [$this->orgId->get()],
        );

        return $row !== null ? (int) $row['cnt'] : 0;
    }

    public function save(User $user): int
    {
        $now = $this->clock->now()->format('Y-m-d H:i:s');

        try {
            // The organization is forced from the request-scoped holder.
            $this->query->execute(
                'INSERT INTO users (email, password_hash, role, organization_id, status, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [
                    $user->email,
                    $user->passwordHash,
                    $user->role->value,
                    $this->orgId->get(),
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

        $now = $this->clock->now()->format('Y-m-d H:i:s');

        // Scoped to the holder org: a user in another organization cannot be updated.
        $affected = $this->query->execute(
            'UPDATE users SET email = ?, password_hash = ?, role = ?, status = ?, updated_at = ? WHERE id = ? AND organization_id = ?',
            [
                $user->email,
                $user->passwordHash,
                $user->role->value,
                $user->status,
                $now,
                $user->id,
                $this->orgId->get(),
            ],
        );

        if ($affected === 0 && $this->findInOrganization($user->id) === null) {
            throw new UserNotFoundException($user->id);
        }
    }

    public function delete(int $id): void
    {
        if ($this->findInOrganization($id) === null) {
            throw new UserNotFoundException($id);
        }

        $this->query->execute('DELETE FROM users WHERE id = ? AND organization_id = ?', [$id, $this->orgId->get()]);
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
