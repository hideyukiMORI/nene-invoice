<?php

declare(strict_types=1);

namespace NeneInvoice\PaymentLink;

/** Result of processing a settlement event, so the webhook can choose a status. */
enum SettlementOutcome
{
    /** Payment recorded (or idempotently already recorded) and link marked paid. */
    case Recorded;
    /** The event could not be mapped to a known link — acknowledged, not retried. */
    case LinkNotFound;
    /** The link resolved but the invoice was not in a recordable state — ignored. */
    case Ignored;
}
