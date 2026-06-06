<?php

declare(strict_types=1);

namespace NeneInvoice\User;

interface ListUsersUseCaseInterface
{
    public function execute(int $limit, int $offset): ListUsersResult;
}
