import { describe, expect, it } from 'vitest'
import { computeDocumentTotals, negativeTaxRateGroup } from './tax'

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

describe('discount lines (#787)', () => {
  it('nets a discount within its rate before rounding', () => {
    const totals = computeDocumentTotals([
      { quantity: 1, unit_price_cents: 330000, tax_rate_bps: 1000 },
      { quantity: 1, unit_price_cents: -100000, tax_rate_bps: 1000 },
    ])
    expect(totals).toEqual({ subtotal_cents: 230000, tax_cents: 23000, total_cents: 253000 })
  })

  it('leaves the other rate untouched when only one is discounted', () => {
    const lines = [
      { quantity: 1, unit_price_cents: 10000, tax_rate_bps: 1000 },
      { quantity: 1, unit_price_cents: 3105, tax_rate_bps: 800 },
      { quantity: 1, unit_price_cents: -2000, tax_rate_bps: 1000 },
    ]
    expect(computeDocumentTotals(lines)).toEqual({
      subtotal_cents: 11105,
      tax_cents: 1048,
      total_cents: 12153,
    })
    expect(negativeTaxRateGroup(lines)).toBeNull()
  })

  it('reports the rate group that nets below zero', () => {
    expect(
      negativeTaxRateGroup([
        { quantity: 1, unit_price_cents: 10000, tax_rate_bps: 1000 },
        { quantity: 1, unit_price_cents: 1000, tax_rate_bps: 800 },
        { quantity: 1, unit_price_cents: -1500, tax_rate_bps: 800 },
      ]),
    ).toBe(800)
  })

  it('accepts a discount that brings the document to exactly zero', () => {
    const lines = [
      { quantity: 1, unit_price_cents: 50000, tax_rate_bps: 1000 },
      { quantity: 1, unit_price_cents: -50000, tax_rate_bps: 1000 },
    ]
    expect(negativeTaxRateGroup(lines)).toBeNull()
    expect(computeDocumentTotals(lines)).toEqual({
      subtotal_cents: 0,
      tax_cents: 0,
      total_cents: 0,
    })
  })
})
