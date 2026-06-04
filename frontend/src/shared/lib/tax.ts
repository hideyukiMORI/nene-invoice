/**
 * Client-side **preview** of a document's totals. Mirrors the backend
 * `TaxCalculator` (ADR 0004 / accounting-compliance.md §3): tax is summed per
 * rate, then half-up rounded **once per rate** — never per line. Integer cents
 * only (no floats). The backend remains authoritative; this only drives the live
 * total shown while editing.
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

/** Half-up rounding of `taxable * bps / 10000`, integer-only (non-negative). */
function roundHalfUp(taxableCents: number, rateBps: number): number {
  return Math.floor((taxableCents * rateBps + 5000) / 10000)
}

export function computeDocumentTotals(lines: TaxableLine[]): DocumentTotals {
  const taxableByRate = new Map<number, number>()
  for (const line of lines) {
    const lineSubtotal = safeInt(line.quantity) * safeInt(line.unit_price_cents)
    taxableByRate.set(line.tax_rate_bps, (taxableByRate.get(line.tax_rate_bps) ?? 0) + lineSubtotal)
  }

  let subtotal = 0
  let tax = 0
  for (const [rateBps, taxable] of [...taxableByRate.entries()].sort((a, b) => a[0] - b[0])) {
    subtotal += taxable
    tax += roundHalfUp(taxable, rateBps)
  }

  return { subtotal_cents: subtotal, tax_cents: tax, total_cents: subtotal + tax }
}
