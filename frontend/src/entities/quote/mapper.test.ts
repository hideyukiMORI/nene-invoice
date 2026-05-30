import { describe, expect, it } from 'vitest'
import type { QuoteDto, QuoteWithLinesDto } from './api-types'
import { toQuote, toQuotePage, toQuoteWithLines } from './mapper'

const dto: QuoteDto = {
  id: 7,
  organization_id: 1,
  client_id: 5,
  quote_number: 'EST-2026-007',
  status: 'draft',
  subtotal_cents: 100000,
  tax_cents: 10000,
  total_cents: 110000,
}

describe('toQuote', () => {
  it('brands the id and maps the core fields', () => {
    const quote = toQuote(dto)
    expect(quote.id).toBe(7)
    expect(quote.quote_number).toBe('EST-2026-007')
    expect(quote.status).toBe('draft')
    expect(quote.total_cents).toBe(110000)
  })

  it('normalises optional fields to null', () => {
    const quote = toQuote(dto)
    expect(quote.issued_at).toBeNull()
    expect(quote.valid_until).toBeNull()
    expect(quote.notes).toBeNull()
  })
})

describe('toQuotePage', () => {
  it('maps items and pagination', () => {
    const page = toQuotePage({ items: [dto], total: 1, limit: 20, offset: 0 })
    expect(page.items).toHaveLength(1)
    expect(page.items[0]?.id).toBe(7)
    expect(page.total).toBe(1)
    expect(page.limit).toBe(20)
  })
})

describe('toQuoteWithLines', () => {
  it('computes line_subtotal_cents when absent (quantity × unit price)', () => {
    const withLines: QuoteWithLinesDto = {
      ...dto,
      line_items: [
        { description: '作業', quantity: 3, unit_price_cents: 1000, tax_rate_bps: 1000 },
      ],
    }
    expect(toQuoteWithLines(withLines).line_items[0]?.line_subtotal_cents).toBe(3000)
  })

  it('keeps a provided line_subtotal_cents', () => {
    const withLines: QuoteWithLinesDto = {
      ...dto,
      line_items: [
        {
          description: 'x',
          quantity: 2,
          unit_price_cents: 1000,
          tax_rate_bps: 1000,
          line_subtotal_cents: 9999,
        },
      ],
    }
    expect(toQuoteWithLines(withLines).line_items[0]?.line_subtotal_cents).toBe(9999)
  })

  it('defaults to an empty line list when none are provided', () => {
    expect(toQuoteWithLines({ ...dto }).line_items).toEqual([])
  })
})
