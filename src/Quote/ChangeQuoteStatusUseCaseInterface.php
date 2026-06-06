<?php

declare(strict_types=1);

namespace NeneInvoice\Quote;

interface ChangeQuoteStatusUseCaseInterface
{
    public function execute(?int $actorUserId, int $id, QuoteStatus $target): Quote;
}
