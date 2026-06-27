import { describe, expect, it } from 'vitest'
import type { RecurringInvoiceDto, RecurringInvoiceWithLinesDto } from './api-types'
import { toRecurringInvoice, toRecurringInvoicePage, toRecurringInvoiceWithLines } from './mapper'

const dto: RecurringInvoiceDto = {
  id: 7,
  organization_id: 1,
  client_id: 5,
  client_name: '得意先ABC',
  name: '月次顧問料',
  frequency: 'monthly',
  subtotal_cents: 100000,
  tax_cents: 10000,
  total_cents: 110000,
  next_run_on: '2026-07-01',
  is_active: true,
}

describe('toRecurringInvoice', () => {
  it('brands the id and normalises optional fields', () => {
    const recurring = toRecurringInvoice(dto)
    expect(recurring.id).toBe(7)
    expect(recurring.name).toBe('月次顧問料')
    expect(recurring.frequency).toBe('monthly')
    expect(recurring.next_run_on).toBe('2026-07-01')
    expect(recurring.last_run_on).toBeNull()
    expect(recurring.notes).toBeNull()
    expect(recurring.is_active).toBe(true)
  })
})

describe('toRecurringInvoicePage', () => {
  it('maps items and pagination', () => {
    const page = toRecurringInvoicePage({ items: [dto], total: 1, limit: 100, offset: 0 })
    expect(page.items).toHaveLength(1)
    expect(page.items[0]?.id).toBe(7)
    expect(page.total).toBe(1)
  })
})

describe('toRecurringInvoiceWithLines', () => {
  it('maps the line template and derives the line subtotal when absent', () => {
    const withLines: RecurringInvoiceWithLinesDto = {
      ...dto,
      line_items: [
        { description: 'Consulting', quantity: 2, unit_price_cents: 50000, tax_rate_bps: 1000 },
      ],
    }
    const recurring = toRecurringInvoiceWithLines(withLines)
    expect(recurring.line_items).toHaveLength(1)
    expect(recurring.line_items[0]?.line_subtotal_cents).toBe(100000)
  })
})
