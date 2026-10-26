/**
 * Client-side **preview** of a document's totals. Mirrors the backend
 * `TaxCalculator` (ADR 0004 / accounting-compliance.md §3): tax is summed per
 * rate, then half-up rounded **once per rate** — never per line. Integer cents
 * only (no floats). The backend remains authoritative; this only drives the live
 * total shown while editing.
 *
 * A line may carry a negative unit price (a discount line, #787); it nets against
 * the other lines of its rate. A rate group that nets below zero is refused by the
 * backend — {@link negativeTaxRateGroup} lets a form say so before submitting.
 */

export interface TaxableLine {
  quantity: number
  unit_price_cents: number
  tax_rate_bps: number
}

export interface DocumentTotals {
  subtotal_cents: number
  tax_cents: number
  total_cents: number
}

const safeInt = (value: number): number => (Number.isFinite(value) ? Math.trunc(value) : 0)

/** Half-up rounding of `taxable * bps / 10000`, integer-only. Exact for non-negative amounts only. */
function roundHalfUp(taxableCents: number, rateBps: number): number {
  return Math.floor((taxableCents * rateBps + 5000) / 10000)
}

function sumByRate(lines: TaxableLine[]): Map<number, number> {
  const taxableByRate = new Map<number, number>()
  for (const line of lines) {
    const lineSubtotal = safeInt(line.quantity) * safeInt(line.unit_price_cents)
    taxableByRate.set(line.tax_rate_bps, (taxableByRate.get(line.tax_rate_bps) ?? 0) + lineSubtotal)
  }
  return taxableByRate
}

/**
 * The lowest tax rate (bps) whose lines net below zero — discounts outweighing
 * the charges at that rate — or `null` when every rate group is non-negative.
 */
export function negativeTaxRateGroup(lines: TaxableLine[]): number | null {
  const negative = [...sumByRate(lines).entries()]
    .filter(([, taxable]) => taxable < 0)
    .map(([rateBps]) => rateBps)
  return negative.length > 0 ? Math.min(...negative) : null
}

export function computeDocumentTotals(lines: TaxableLine[]): DocumentTotals {
  const taxableByRate = sumByRate(lines)

  let subtotal = 0
  let tax = 0
  for (const [rateBps, taxable] of [...taxableByRate.entries()].sort((a, b) => a[0] - b[0])) {
    subtotal += taxable
    tax += roundHalfUp(taxable, rateBps)
  }

  return { subtotal_cents: subtotal, tax_cents: tax, total_cents: subtotal + tax }
}
