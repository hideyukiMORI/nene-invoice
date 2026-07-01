<?php

declare(strict_types=1);

namespace NeneInvoice\Tests\Support;

use NeneInvoice\Auth\Role;
use NeneInvoice\Organization\InitialAdminRepositoryInterface;
use NeneInvoice\User\User;
use NeneInvoice\User\UserEmailConflictException;

/**
 * In-memory fake for the cross-tenant initial-admin provisioner. Emails are
 * globally unique (mirroring the DB constraint), so a duplicate throws.
 */
final class InMemoryInitialAdminRepository implements InitialAdminRepositoryInterface
{
    /** @var list<User> */
    public array $created = [];
    private int $nextId = 1;

    public function createInitialAdmin(int $organizationId, string $email, string $passwordHash): User
    {
        foreach ($this->created as $existing) {
            if ($existing->email === $email) {
                throw new UserEmailConflictException($email);
            }
        }

        $user = new User(
            email: $email,
            passwordHash: $passwordHash,
            role: Role::Admin,
            organizationId: $organizationId,
            status: 'active',
            id: $this->nextId++,
            createdAt: '2026-05-29 00:00:00',
            updatedAt: '2026-05-29 00:00:00',
        );

        $this->created[] = $user;

        return $user;
    }
}
