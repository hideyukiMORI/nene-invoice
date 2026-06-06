<?php

declare(strict_types=1);

namespace NeneInvoice\Template;

final readonly class ListTemplatesUseCase implements ListTemplatesUseCaseInterface
{
    public function __construct(
        private TemplateRepositoryInterface $templates,
    ) {
    }

    public function execute(int $limit, int $offset): ListTemplatesResult
    {
        return new ListTemplatesResult(
            $this->templates->findAll($limit, $offset),
            $this->templates->count(),
        );
    }
}
