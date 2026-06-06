<?php

declare(strict_types=1);

namespace NeneInvoice\Template;

use Closure;
use LogicException;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\RequestScopedHolder;
use NeneInvoice\Audit\AuditRecorderInterface;
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
        private AuditRecorderInterface $audit,
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
        /** @var array<string, mixed> $before */
        $before = [];

        $result = $this->tx->transactional(function (DatabaseQueryExecutorInterface $exec) use (
            $id,
            $input,
            &$before,
        ): TemplateWithLines {
            $templates = ($this->templatesFactory)($exec);
            $lineItems = ($this->lineItemsFactory)($exec);

            $existing = $templates->findById($id);
            if ($existing === null) {
                throw new TemplateNotFoundException($id);
            }

            $before = TemplateResponse::toArray($existing, $lineItems->findByParent(LineItemParent::Template, $id));

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

            return new TemplateWithLines($updated, $lineItems->findByParent(LineItemParent::Template, $id));
        });

        $this->audit->record($actorUserId, $this->orgId->get(), 'template.updated', 'template', $id, $before, TemplateResponse::toArray($result->template, $result->lines));

        return $result;
    }
}
