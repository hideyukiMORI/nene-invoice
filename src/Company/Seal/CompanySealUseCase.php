<?php

declare(strict_types=1);

namespace NeneInvoice\Company\Seal;

use Closure;
use Nene2\Audit\AuditEvent;
use Nene2\Audit\AuditRecorderFactoryInterface;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\RequestScopedHolder;

final readonly class CompanySealUseCase implements CompanySealUseCaseInterface
{
    /**
     * @param Closure(DatabaseQueryExecutorInterface): CompanySealRepositoryInterface $repositoryFactory
     * @param RequestScopedHolder<int>                                                 $orgId
     */
    public function __construct(
        private CompanySealRepositoryInterface $repository,
        private DatabaseTransactionManagerInterface $tx,
        private Closure $repositoryFactory,
        private AuditRecorderFactoryInterface $auditFactory,
        private RequestScopedHolder $orgId,
    ) {
    }

    public function get(): ?string
    {
        return $this->repository->find();
    }

    public function save(?int $actorUserId, string $imageBase64): void
    {
        $organizationId = $this->orgId->get();
        $existed        = $this->repository->exists();

        $this->tx->transactional(function (DatabaseQueryExecutorInterface $exec) use ($actorUserId, $organizationId, $existed, $imageBase64): void {
            ($this->repositoryFactory)($exec)->save($imageBase64);

            // The base64 bytes are never written to the audit trail (large and
            // sensitive); only the presence transition is recorded.
            $this->auditFactory->forExecutor($exec)->record(new AuditEvent(
                action: 'company_settings.seal_updated',
                entityType: 'company_settings',
                entityId: $organizationId,
                actorId: $actorUserId,
                organizationId: $organizationId,
                before: ['has_seal' => $existed],
                after: ['has_seal' => true],
            ));
        });
    }

    public function delete(?int $actorUserId): void
    {
        $organizationId = $this->orgId->get();
        $existed        = $this->repository->exists();

        $this->tx->transactional(function (DatabaseQueryExecutorInterface $exec) use ($actorUserId, $organizationId, $existed): void {
            ($this->repositoryFactory)($exec)->delete();

            $this->auditFactory->forExecutor($exec)->record(new AuditEvent(
                action: 'company_settings.seal_deleted',
                entityType: 'company_settings',
                entityId: $organizationId,
                actorId: $actorUserId,
                organizationId: $organizationId,
                before: ['has_seal' => $existed],
                after: ['has_seal' => false],
            ));
        });
    }
}
