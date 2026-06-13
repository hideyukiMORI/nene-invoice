<?php

declare(strict_types=1);

namespace NeneInvoice\PaymentLink;

/** Result of a revoke attempt, so the handler can pick the right HTTP status. */
enum RevokeOutcome
{
    /** An active link transitioned to revoked. */
    case Revoked;
    /** The link exists but was already terminal (revoked/paid); revoke is a no-op. */
    case AlreadyInactive;
    /** No link with that id in the caller's organization. */
    case NotFound;
}
