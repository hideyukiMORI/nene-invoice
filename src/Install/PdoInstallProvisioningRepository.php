<?php

declare(strict_types=1);

namespace NeneInvoice\Install;

use Nene2\Database\DatabaseConstraintException;
use Nene2\Database\DatabaseQueryExecutorInterface;
use NeneInvoice\Auth\Role;
use NeneInvoice\User\User;
use NeneInvoice\User\UserEmailConflictException;

/**
 * Cross-tenant first-run inserts for the web installer. Mirrors
 * {@see \NeneInvoice\Organization\PdoInitialAdminRepository}: the organization id
 * is supplied by the caller (or NULL for a superadmin) rather than a
 * request-scoped holder, which the org-scoped repositories cannot express.
 */
final readonly class PdoInstallProvisioningRepository implements InstallProvisioningRepositoryInterface
{
    public function __construct(
        private DatabaseQueryExecutorInterface $query,
    ) {
    }

    public function seedCompanySettings(int $organizationId, string $legalName): void
    {
        $now = date('Y-m-d H:i:s');

        $this->query->execute(
            'INSERT INTO company_settings (organization_id, legal_name, created_at, updated_at)
             VALUES (?, ?, ?, ?)',
            [$organizationId, $legalName, $now, $now],
        );
    }

    public function createInitialSuperadmin(string $email, string $passwordHash): User
    {
        $now  = date('Y-m-d H:i:s');
        $role = Role::Superadmin;

        try {
            $this->query->execute(
                'INSERT INTO users (email, password_hash, role, organization_id, status, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$email, $passwordHash, $role->value, null, 'active', $now, $now],
            );
        } catch (DatabaseConstraintException $e) {
            // users.email is globally UNIQUE — surface a clean domain conflict.
            throw new UserEmailConflictException($email, $e);
        }

        return new User(
            email: $email,
            passwordHash: $passwordHash,
            role: $role,
            organizationId: null,
            status: 'active',
            id: $this->query->lastInsertId(),
            createdAt: $now,
            updatedAt: $now,
        );
    }
}
