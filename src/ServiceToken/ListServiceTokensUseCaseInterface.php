<?php

declare(strict_types=1);

namespace NeneInvoice\ServiceToken;

interface ListServiceTokensUseCaseInterface
{
    public function execute(int $limit, int $offset): ListServiceTokensResult;
}
