<?php

declare(strict_types=1);

namespace NeneInvoice\Client;

use RuntimeException;

final class ClientNotFoundException extends RuntimeException
{
    public function __construct(int $id)
    {
        parent::__construct("Client {$id} not found.");
    }
}
