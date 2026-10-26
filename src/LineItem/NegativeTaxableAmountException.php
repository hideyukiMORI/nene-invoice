<?php

declare(strict_types=1);

namespace NeneInvoice\LineItem;

use DomainException;

/**
 * A tax-rate group's taxable amount came out negative: discount lines (negative
 * unit price) outweigh the charges they discount at that rate (Issue #787).
 *
 * {@see TaxCalculator} refuses such a document rather than compute negative tax.
 * Use cases that accept line items catch this and re-throw their own validation
 * exception (→ 422 Problem Details); the message is user-facing.
 */
final class NegativeTaxableAmountException extends DomainException
{
    public function __construct(public readonly int $taxRateBps)
    {
        parent::__construct(sprintf(
            '%d%% 対象の合計がマイナスになります。値引きは同じ税率の明細の合計を超えられません。',
            intdiv($taxRateBps, 100),
        ));
    }
}
