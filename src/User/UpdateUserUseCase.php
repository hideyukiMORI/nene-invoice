<?php

declare(strict_types=1);

namespace NeneInvoice\User;

use LogicException;
use NeneInvoice\Auth\Role;

final readonly class UpdateUserUseCase
{
    public function __construct(
        private UserRepositoryInterface $users,
    ) {
    }

    /**
     * Updates a user's role, status, and (optionally) password — only within the
     * caller's organization. A user from another organization is reported as not
     * found. Email is immutable here.
     *
     * @throws UserNotFoundException        when the user is missing or in another org
     * @throws RoleNotAssignableException   when attempting to assign superadmin
     */
    public function execute(int $organizationId, int $userId, UpdateUserInput $input): User
    {
        $existing = $this->users->findById($userId);

        if ($existing === null || $existing->organizationId !== $organizationId) {
            throw new UserNotFoundException($userId);
        }

        if ($input->role === Role::Superadmin) {
            throw new RoleNotAssignableException($input->role);
        }

        $this->users->update(new User(
            email: $existing->email,
            passwordHash: $input->password !== null ? password_hash($input->password, PASSWORD_DEFAULT) : $existing->passwordHash,
            role: $input->role,
            organizationId: $existing->organizationId,
            status: $input->status,
            id: $existing->id,
            createdAt: $existing->createdAt,
            updatedAt: $existing->updatedAt,
        ));

        $updated = $this->users->findById($userId);

        if ($updated === null) {
            throw new LogicException('User disappeared immediately after update.');
        }

        return $updated;
    }
}
