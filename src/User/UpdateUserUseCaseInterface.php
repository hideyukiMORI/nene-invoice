<?php

declare(strict_types=1);

namespace NeneInvoice\User;

interface UpdateUserUseCaseInterface
{
    public function execute(?int $actorUserId, int $userId, UpdateUserInput $input): User;
}
