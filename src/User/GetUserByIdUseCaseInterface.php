<?php

declare(strict_types=1);

namespace NeneInvoice\User;

interface GetUserByIdUseCaseInterface
{
    public function execute(int $id): User;
}
