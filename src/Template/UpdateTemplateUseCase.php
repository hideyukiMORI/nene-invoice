<?php

declare(strict_types=1);

namespace NeneInvoice\Template;

use Closure;
use LogicException;
use Nene2\Audit\AuditEvent;
use Nene2\Audit\AuditRecorderFactoryInterface;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\LineItem\LineItem;
use NeneInvoice\LineItem\LineItemParent;
use NeneInvoice\LineItem\LineItemRepositoryInterface;

final readonly class UpdateTemplateUseCase implements UpdateTemplateUseCaseInterface
{
    /**
     * @param Closure(DatabaseQueryExecutorInterface): TemplateRepositoryInterface $templatesFactory
     * @param Closure(DatabaseQueryExecutorInterface): LineItemRepositoryInterface $lineItemsFactory
     * @param RequestScopedHolder<int> $orgId resolved organization for this request
     */
    public function __construct(
        private DatabaseTransactionManagerInterface $tx,
        private Closure $templatesFactory,
        private Closure $lineItemsFactory,
        private AuditRecorderFactoryInterface $auditFactory,
        private RequestScopedHolder $orgId,
    ) {
    }

    /**
     * Updates a template (header + line presets) atomically in the resolved
     * organization.
     *
     * @throws TemplateNotFoundException
     */
    public function execute(?int $actorUserId, int $id, UpdateTemplateInput $input): TemplateWithLines
    {
        $organizationId = $this->orgId->get();

        return $this->tx->transactional(function (DatabaseQueryExecutorInterface $exec) use (
            $actorUserId,
            $organizationId,
            $id,
            $input,
        ): TemplateWithLines {
            $templates = ($this->templatesFactory)($exec);
            $lineItems = ($this->lineItemsFactory)($exec);

            $existing = $templates->findById($id);
            if ($existing === null) {
                throw new TemplateNotFoundException($id);
            }

            $before = TemplateResponse::toArray($existing, $lineItems->findByParent(LineItemParent::Template, $id));
            // (captured for the in-transaction audit below)

            $templates->update(new Template(
                organizationId: $existing->organizationId,
                name: $input->name,
                notes: $input->notes,
                isDeleted: false,
                id: $existing->id,
                createdAt: $existing->createdAt,
                updatedAt: $existing->updatedAt,
            ));

            $entities = [];
            foreach ($input->lines as $index => $line) {
                $entities[] = new LineItem(
                    parentType: LineItemParent::Template,
                    parentId: $id,
                    description: $line->description,
                    quantity: $line->quantity,
                    unitPriceCents: $line->unitPriceCents,
                    taxRateBps: $line->taxRateBps,
                    sortOrder: $index,
                );
            }
            $lineItems->replaceForParent(LineItemParent::Template, $id, $entities);

            $updated = $templates->findById($id);
            if ($updated === null) {
                throw new LogicException('Template disappeared immediately after update.');
            }

            $result = new TemplateWithLines($updated, $lineItems->findByParent(LineItemParent::Template, $id));

            // Audit inside the transaction (Issue #352).
            $this->auditFactory->forExecutor($exec)->record(new AuditEvent(
                action: 'template.updated',
                entityType: 'template',
                entityId: $id,
                actorId: $actorUserId,
                organizationId: $organizationId,
                before: $before,
                after: TemplateResponse::toArray($result->template, $result->lines),
            ));

            return $result;
        });
    }
}
