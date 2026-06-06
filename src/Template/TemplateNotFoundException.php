<?php

declare(strict_types=1);

namespace NeneInvoice\Template;

use RuntimeException;

final class TemplateNotFoundException extends RuntimeException
{
    public function __construct(int $id)
    {
        parent::__construct("Template {$id} not found.");
    }
}
