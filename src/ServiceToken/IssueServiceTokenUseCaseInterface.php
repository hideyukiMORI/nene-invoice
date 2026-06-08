<?php

declare(strict_types=1);

namespace NeneInvoice\ServiceToken;

interface IssueServiceTokenUseCaseInterface
{
    /**
     * @param int|null $actorUserId authenticated operator (null for the CLI issuer)
     */
    public function execute(?int $actorUserId, IssueServiceTokenInput $input): IssueServiceTokenResult;
}
