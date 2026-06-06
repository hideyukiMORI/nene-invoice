<?php

declare(strict_types=1);

namespace NeneInvoice\User;

interface DeleteUserUseCaseInterface
{
    public function execute(int $callerUserId, int $userId): void;
}
