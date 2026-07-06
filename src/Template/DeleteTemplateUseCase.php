<?php

declare(strict_types=1);

namespace NeneInvoice\Template;

use Closure;
use Nene2\Audit\AuditEvent;
use Nene2\Audit\AuditRecorderFactoryInterface;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\LineItem\LineItemParent;
use NeneInvoice\LineItem\LineItemRepositoryInterface;

final readonly class DeleteTemplateUseCase implements DeleteTemplateUseCaseInterface
{
    /**
     * @param Closure(DatabaseQueryExecutorInterface): TemplateRepositoryInterface $templatesFactory
     * @param Closure(DatabaseQueryExecutorInterface): LineItemRepositoryInterface $lineItemsFactory
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private TemplateRepositoryInterface $templates,
        private LineItemRepositoryInterface $lineItems,
        private DatabaseTransactionManagerInterface $tx,
        private Closure $templatesFactory,
        private Closure $lineItemsFactory,
        private AuditRecorderFactoryInterface $auditFactory,
        private RequestScopedHolder $orgId,
    ) {
    }

    /**
     * Soft-deletes a template and clears its line presets, in the resolved
     * organization (the repository scopes the lookup/delete to the request org).
     * The two deletes and the audit record commit atomically (Issue #352).
     *
     * @throws TemplateNotFoundException
     */
    public function execute(?int $actorUserId, int $id): void
    {
        $existing = $this->templates->findById($id);
        if ($existing === null) {
            throw new TemplateNotFoundException($id);
        }

        $before         = TemplateResponse::toArray($existing, $this->lineItems->findByParent(LineItemParent::Template, $id));
        $organizationId = $this->orgId->get();

        $this->tx->transactional(function (DatabaseQueryExecutorInterface $exec) use ($actorUserId, $organizationId, $id, $before): null {
            ($this->templatesFactory)($exec)->delete($id);
            ($this->lineItemsFactory)($exec)->deleteForParent(LineItemParent::Template, $id);

            $this->auditFactory->forExecutor($exec)->record(new AuditEvent(
                action: 'template.deleted',
                entityType: 'template',
                entityId: $id,
                actorId: $actorUserId,
                organizationId: $organizationId,
                before: $before,
                after: null,
            ));

            return null;
        });
    }
}
