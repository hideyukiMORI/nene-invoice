<?php

declare(strict_types=1);

namespace NeneInvoice\User;

use LogicException;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Audit\AuditRecorderInterface;
use NeneInvoice\Auth\Role;

final readonly class CreateUserUseCase
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
     * Creates a user in the caller's organization. The organization is taken
     * from the resolved org holder, never from request input, so a user cannot
     * be created in another tenant.
     *
     * @throws RoleNotAssignableException   when attempting to assign superadmin
     * @throws UserEmailConflictException   when the email is already in use
     */
    public function execute(?int $actorUserId, CreateUserInput $input): User
    {
        if ($input->role === Role::Superadmin) {
            throw new RoleNotAssignableException($input->role);
        }

        $organizationId = $this->orgId->get();

        $id = $this->users->save(new User(
            email: $input->email,
            passwordHash: password_hash($input->password, PASSWORD_DEFAULT),
            role: $input->role,
            organizationId: $organizationId,
            status: 'active',
        ));

        $created = $this->users->findById($id);

        if ($created === null) {
            throw new LogicException('User disappeared immediately after creation.');
        }

        $this->audit->record($actorUserId, $organizationId, 'user.created', 'user', $id, null, UserResponse::toArray($created));

        return $created;
    }
}
