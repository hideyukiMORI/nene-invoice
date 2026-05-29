<?php

declare(strict_types=1);

namespace NeneInvoice\Quote;

/**
 * Quote lifecycle states and allowed transitions (domain-model.md):
 * draft → sent → accepted / rejected / expired. Accepted/rejected/expired are
 * terminal here (acceptance leads to invoice conversion in the Invoice domain).
 */
enum QuoteStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Expired = 'expired';

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Draft => $target === self::Sent,
            self::Sent => in_array($target, [self::Accepted, self::Rejected, self::Expired], true),
            self::Accepted, self::Rejected, self::Expired => false,
        };
    }
}
