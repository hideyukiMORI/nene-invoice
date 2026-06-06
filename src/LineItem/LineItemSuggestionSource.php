<?php

declare(strict_types=1);

namespace NeneInvoice\LineItem;

/**
 * Where a line-item suggestion came from (#323 PR-3). `master` rows are the
 * authoritative item master (品目); `history` rows are derived from past
 * documents and shown only when not already covered by the master.
 */
enum LineItemSuggestionSource: string
{
    case Master = 'master';
    case History = 'history';
}
