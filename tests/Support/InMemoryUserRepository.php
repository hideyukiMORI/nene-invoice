<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Support;

use NeneInvoice\User\User;
use NeneInvoice\User\UserEmailConflictException;
use NeneInvoice\User\UserNotFoundException;
use NeneInvoice\User\UserRepositoryInterface;

/**
 * In-memory fake for use-case tests (no database).
 */
final class InMemoryUserRepository implements UserRepositoryInterface
{
    /** @var array<int, User> */
    private array $byId = [];
    private int $nextId = 1;

    public function findById(int $id): ?User
    {
        return $this->byId[$id] ?? null;
    }

    public function findByEmail(string $email): ?User
    {
        foreach ($this->byId as $user) {
            if ($user->email === $email) {
                return $user;
            }
        }

        return null;
    }

    /** @return list<User> */
    public function findAllByOrganization(int $organizationId, int $limit, int $offset): array
    {
        $matches = array_values(array_filter(
            $this->byId,
            static fn (User $u): bool => $u->organizationId === $organizationId,
        ));

        return array_slice($matches, $offset, $limit);
    }

    public function countByOrganization(int $organizationId): int
    {
        return count(array_filter(
            $this->byId,
            static fn (User $u): bool => $u->organizationId === $organizationId,
        ));
    }

    public function save(User $user): int
    {
        if ($this->findByEmail($user->email) !== null) {
            throw new UserEmailConflictException($user->email);
        }

        $id = $this->nextId++;
        $now = '2026-05-29 00:00:00';

        $this->byId[$id] = new User(
            email: $user->email,
            passwordHash: $user->passwordHash,
            role: $user->role,
            organizationId: $user->organizationId,
            status: $user->status,
            id: $id,
            createdAt: $now,
            updatedAt: $now,
        );

        return $id;
    }

    public function update(User $user): void
    {
        if ($user->id === null || !isset($this->byId[$user->id])) {
            throw new UserNotFoundException($user->id ?? 0);
        }

        $this->byId[$user->id] = $user;
    }

    public function delete(int $id): void
    {
        if (!isset($this->byId[$id])) {
            throw new UserNotFoundException($id);
        }

        unset($this->byId[$id]);
    }
}
