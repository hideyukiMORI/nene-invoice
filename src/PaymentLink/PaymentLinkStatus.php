<?php

declare(strict_types=1);

namespace NeneInvoice\PaymentLink;

/**
 * Persisted lifecycle state of a {@see PaymentLink}.
 *
 * Expiry is **not** a stored status — it is derived from `expires_at` at read
 * time ({@see PaymentLink::isExpired()}), so an `active` link past its expiry is
 * treated as expired without a background sweep. String values are registered in
 * `docs/explanation/terminology.md` (binding).
 */
enum PaymentLinkStatus: string
{
    case Active = 'active';
    case Paid = 'paid';
    case Revoked = 'revoked';
}
