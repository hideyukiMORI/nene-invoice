<?php

declare(strict_types=1);

namespace NeneInvoice\User;

use Closure;
use Nene2\Audit\AuditEvent;
use Nene2\Audit\AuditRecorderFactoryInterface;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\RequestScopedHolder;

final readonly class DeleteUserUseCase implements DeleteUserUseCaseInterface
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
     * Deletes a user within the caller's organization. Cross-organization targets
     * are reported as not found, and a caller cannot delete their own account. The
     * delete and its audit record commit atomically (Issue #352).
     *
     * @throws CannotDeleteSelfException
     * @throws UserNotFoundException
     */
    public function execute(int $callerUserId, int $userId): void
    {
        if ($userId === $callerUserId) {
            throw new CannotDeleteSelfException();
        }

        $existing = $this->users->findInOrganization($userId);

        if ($existing === null) {
            throw new UserNotFoundException($userId);
        }

        $organizationId = $this->orgId->get();

        $this->tx->transactional(function (DatabaseQueryExecutorInterface $exec) use ($callerUserId, $organizationId, $userId, $existing): null {
            ($this->usersFactory)($exec)->delete($userId);

            $this->auditFactory->forExecutor($exec)->record(new AuditEvent(
                action: 'user.deleted',
                entityType: 'user',
                entityId: $userId,
                actorId: $callerUserId,
                organizationId: $organizationId,
                before: UserResponse::toArray($existing),
                after: null,
            ));

            return null;
        });
    }
}
