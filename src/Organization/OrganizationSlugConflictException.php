<?php

declare(strict_types=1);

namespace NeneInvoice\Organization;

use RuntimeException;
use Throwable;

final class OrganizationSlugConflictException extends RuntimeException
{
    public function __construct(string $slug, ?Throwable $previous = null)
    {
        parent::__construct("Organization slug '{$slug}' is already in use.", 0, $previous);
    }
}
