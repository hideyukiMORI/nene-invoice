<?php

declare(strict_types=1);

namespace NeneInvoice\Auth;

use NeneInvoice\User\User;

interface GetCurrentUserUseCaseInterface
{
    public function execute(int $userId): ?User;
}
