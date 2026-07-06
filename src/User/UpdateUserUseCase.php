<?php

declare(strict_types=1);

namespace NeneInvoice\User;

use Closure;
use LogicException;
use Nene2\Audit\AuditEvent;
use Nene2\Audit\AuditRecorderFactoryInterface;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Auth\Role;

final readonly class UpdateUserUseCase implements UpdateUserUseCaseInterface
{
    /**
     * @param Closure(DatabaseQueryExecutorInterface): UserRepositoryInterface $usersFactory
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private UserRepositoryInterface $users,
        private DatabaseTransactionManagerInterface $tx,
        private Closure $usersFactory,
        private AuditRecorderFactoryInterface $auditFactory,
        private RequestScopedHolder $orgId,
    ) {
    }

    /**
     * Updates a user's role, status, and (optionally) password — only within the
     * caller's organization. A user from another organization is reported as not
     * found. Email is immutable here. The write and its audit record commit
     * atomically (Issue #352).
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

        $organizationId = $this->orgId->get();
        $passwordHash   = $input->password !== null ? password_hash($input->password, PASSWORD_DEFAULT) : $existing->passwordHash;

        return $this->tx->transactional(function (DatabaseQueryExecutorInterface $exec) use ($actorUserId, $organizationId, $userId, $input, $existing, $passwordHash): User {
            $users = ($this->usersFactory)($exec);

            $users->update(new User(
                email: $existing->email,
                passwordHash: $passwordHash,
                role: $input->role,
                organizationId: $existing->organizationId,
                status: $input->status,
                id: $existing->id,
                createdAt: $existing->createdAt,
                updatedAt: $existing->updatedAt,
            ));

            $updated = $users->findById($userId);

            if ($updated === null) {
                throw new LogicException('User disappeared immediately after update.');
            }

            $this->auditFactory->forExecutor($exec)->record(new AuditEvent(
                action: 'user.updated',
                entityType: 'user',
                entityId: $userId,
                actorId: $actorUserId,
                organizationId: $organizationId,
                before: UserResponse::toArray($existing),
                after: UserResponse::toArray($updated),
            ));

            return $updated;
        });
    }
}
