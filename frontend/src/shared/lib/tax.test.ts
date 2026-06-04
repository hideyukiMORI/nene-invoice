import { describe, expect, it } from 'vitest'
import { computeDocumentTotals } from './tax'

describe('computeDocumentTotals', () => {
  it('sums a single rate and rounds tax once', () => {
    const totals = computeDocumentTotals([
      { quantity: 1, unit_price_cents: 120000, tax_rate_bps: 1000 },
      { quantity: 1, unit_price_cents: 80000, tax_rate_bps: 1000 },
    ])
    expect(totals).toEqual({ subtotal_cents: 200000, tax_cents: 20000, total_cents: 220000 })
  })

  it('rounds tax per rate (half-up), not per line', () => {
    // Two lines at 10% summing to 1005 → tax = round(100.5) = 101, never 50+50.
    const totals = computeDocumentTotals([
      { quantity: 1, unit_price_cents: 502, tax_rate_bps: 1000 },
      { quantity: 1, unit_price_cents: 503, tax_rate_bps: 1000 },
    ])
    expect(totals).toEqual({ subtotal_cents: 1005, tax_cents: 101, total_cents: 1106 })
  })

  it('handles mixed tax rates independently', () => {
    const totals = computeDocumentTotals([
      { quantity: 1, unit_price_cents: 100000, tax_rate_bps: 1000 },
      { quantity: 1, unit_price_cents: 100000, tax_rate_bps: 800 },
    ])
    expect(totals).toEqual({ subtotal_cents: 200000, tax_cents: 18000, total_cents: 218000 })
  })

  it('multiplies quantity by unit price', () => {
    const totals = computeDocumentTotals([
      { quantity: 3, unit_price_cents: 50000, tax_rate_bps: 1000 },
    ])
    expect(totals).toEqual({ subtotal_cents: 150000, tax_cents: 15000, total_cents: 165000 })
  })

  it('coerces NaN (empty inputs) to zero', () => {
    const totals = computeDocumentTotals([
      { quantity: Number.NaN, unit_price_cents: Number.NaN, tax_rate_bps: 1000 },
    ])
    expect(totals).toEqual({ subtotal_cents: 0, tax_cents: 0, total_cents: 0 })
  })
})
