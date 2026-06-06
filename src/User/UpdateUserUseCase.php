<?php

declare(strict_types=1);

namespace NeneInvoice\User;

use LogicException;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Audit\AuditRecorderInterface;
use NeneInvoice\Auth\Role;

final readonly class UpdateUserUseCase implements UpdateUserUseCaseInterface
{
    /**
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private UserRepositoryInterface $users,
        private AuditRecorderInterface $audit,
        private RequestScopedHolder $orgId,
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
    public function execute(?int $actorUserId, int $userId, UpdateUserInput $input): User
    {
        $existing = $this->users->findInOrganization($userId);

        if ($existing === null) {
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

        $this->audit->record($actorUserId, $this->orgId->get(), 'user.updated', 'user', $userId, UserResponse::toArray($existing), UserResponse::toArray($updated));

        return $updated;
    }
}
