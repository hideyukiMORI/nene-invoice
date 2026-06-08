<?php

declare(strict_types=1);

namespace NeneInvoice\ServiceToken;

use RuntimeException;

final class ServiceTokenNotFoundException extends RuntimeException
{
    public function __construct(int $id)
    {
        parent::__construct("Service token {$id} not found.");
    }
}
