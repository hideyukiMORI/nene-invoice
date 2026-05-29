<?php

declare(strict_types=1);

namespace NeneInvoice\Quote;

/**
 * Quote lifecycle states (domain-model.md). Transition rules are enforced by the
 * quote use cases (see the status-transition PR), not here.
 */
enum QuoteStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Expired = 'expired';
}
