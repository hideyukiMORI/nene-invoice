<?php

declare(strict_types=1);

namespace NeneInvoice\User;

interface CreateUserUseCaseInterface
{
    public function execute(?int $actorUserId, CreateUserInput $input): User;
}
