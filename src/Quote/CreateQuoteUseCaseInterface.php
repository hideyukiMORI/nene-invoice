<?php

declare(strict_types=1);

namespace NeneInvoice\Quote;

interface CreateQuoteUseCaseInterface
{
    public function execute(?int $actorUserId, CreateQuoteInput $input): QuoteWithLines;
}
