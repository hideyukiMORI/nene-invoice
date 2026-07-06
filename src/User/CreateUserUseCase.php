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

final readonly class CreateUserUseCase implements CreateUserUseCaseInterface
{
    /**
     * @param Closure(DatabaseQueryExecutorInterface): UserRepositoryInterface $usersFactory
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private DatabaseTransactionManagerInterface $tx,
        private Closure $usersFactory,
        private AuditRecorderFactoryInterface $auditFactory,
        private RequestScopedHolder $orgId,
    ) {
    }

    /**
     * Creates a user in the caller's organization. The organization is taken
     * from the resolved org holder, never from request input, so a user cannot
     * be created in another tenant. The write and its audit record commit
     * atomically (Issue #352).
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
        $passwordHash   = password_hash($input->password, PASSWORD_DEFAULT);

        return $this->tx->transactional(function (DatabaseQueryExecutorInterface $exec) use ($actorUserId, $organizationId, $input, $passwordHash): User {
            $users = ($this->usersFactory)($exec);

            $id = $users->save(new User(
                email: $input->email,
                passwordHash: $passwordHash,
                role: $input->role,
                organizationId: $organizationId,
                status: 'active',
            ));

            $created = $users->findById($id);

            if ($created === null) {
                throw new LogicException('User disappeared immediately after creation.');
            }

            $this->auditFactory->forExecutor($exec)->record(new AuditEvent(
                action: 'user.created',
                entityType: 'user',
                entityId: $id,
                actorId: $actorUserId,
                organizationId: $organizationId,
                before: null,
                after: UserResponse::toArray($created),
            ));

            return $created;
        });
    }
}
