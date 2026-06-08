<?php

declare(strict_types=1);

namespace NeneInvoice\ServiceToken;

interface RevokeServiceTokenUseCaseInterface
{
    /**
     * @throws ServiceTokenNotFoundException when the token does not exist in the caller's org
     */
    public function execute(?int $actorUserId, int $id): void;
}
