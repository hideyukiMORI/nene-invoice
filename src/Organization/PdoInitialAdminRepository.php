<?php

declare(strict_types=1);

namespace NeneInvoice\Organization;

use Nene2\Database\DatabaseConstraintException;
use Nene2\Database\DatabaseQueryExecutorInterface;
use NeneInvoice\Auth\Role;
use NeneInvoice\User\User;
use NeneInvoice\User\UserEmailConflictException;

/**
 * Cross-tenant insert for an organization's initial admin. The organization id
 * is taken from the caller (the newly created org), NOT from any request-scoped
 * holder — deliberately distinct from {@see \NeneInvoice\User\PdoUserRepository}
 * which forces the resolved org and cannot target another tenant.
 */
final readonly class PdoInitialAdminRepository implements InitialAdminRepositoryInterface
{
    public function __construct(
        private DatabaseQueryExecutorInterface $query,
    ) {
    }

    public function createInitialAdmin(int $organizationId, string $email, string $passwordHash): User
    {
        $now  = date('Y-m-d H:i:s');
        $role = Role::Admin;

        try {
            $this->query->execute(
                'INSERT INTO users (email, password_hash, role, organization_id, status, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$email, $passwordHash, $role->value, $organizationId, 'active', $now, $now],
            );
        } catch (DatabaseConstraintException $e) {
            // users.email is globally UNIQUE — surface a clean domain conflict.
            throw new UserEmailConflictException($email, $e);
        }

        return new User(
            email: $email,
            passwordHash: $passwordHash,
            role: $role,
            organizationId: $organizationId,
            status: 'active',
            id: $this->query->lastInsertId(),
            createdAt: $now,
            updatedAt: $now,
        );
    }
}
