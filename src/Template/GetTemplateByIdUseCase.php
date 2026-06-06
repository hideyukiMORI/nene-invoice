<?php

declare(strict_types=1);

namespace NeneInvoice\Template;

use NeneInvoice\LineItem\LineItemParent;
use NeneInvoice\LineItem\LineItemRepositoryInterface;

final readonly class GetTemplateByIdUseCase implements GetTemplateByIdUseCaseInterface
{
    public function __construct(
        private TemplateRepositoryInterface $templates,
        private LineItemRepositoryInterface $lineItems,
    ) {
    }

    /**
     * Fetches a template with its line presets in the current organization. The
     * repository scopes the read to the request org, so a template from another
     * organization (or a missing/soft-deleted id) surfaces as not found.
     *
     * @throws TemplateNotFoundException
     */
    public function execute(int $id): TemplateWithLines
    {
        $template = $this->templates->findById($id);

        if ($template === null) {
            throw new TemplateNotFoundException($id);
        }

        return new TemplateWithLines($template, $this->lineItems->findByParent(LineItemParent::Template, $id));
    }
}
