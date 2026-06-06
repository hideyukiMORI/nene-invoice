<?php

declare(strict_types=1);

namespace NeneInvoice\LineItem;

interface ListLineItemSuggestionsUseCaseInterface
{
    /** @return list<LineItemSuggestion> */
    public function execute(): array;
}
