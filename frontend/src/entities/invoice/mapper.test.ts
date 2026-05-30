import { describe, expect, it } from 'vitest'
import type { InvoiceDto, InvoiceWithLinesDto } from './api-types'
import { toInvoice, toInvoicePage, toInvoiceWithLines } from './mapper'

const dto: InvoiceDto = {
  id: 1,
  organization_id: 1,
  client_id: 5,
  status: 'issued',
  is_overdue: false,
  is_qualified_invoice: true,
  invoice_number: 'INV-2026-001',
  subtotal_cents: 106000,
  tax_cents: 10480,
  total_cents: 116480,
}

describe('toInvoice', () => {
  it('brands the id and preserves snake_case money fields', () => {
    const invoice = toInvoice(dto)
    expect(invoice.id).toBe(1)
    expect(invoice.invoice_number).toBe('INV-2026-001')
    expect(invoice.status).toBe('issued')
    expect(invoice.total_cents).toBe(116480)
  })

  it('normalises missing optional fields to null', () => {
    const invoice = toInvoice({ ...dto, invoice_number: undefined, issued_at: undefined })
    expect(invoice.invoice_number).toBeNull()
    expect(invoice.issued_at).toBeNull()
  })

  it('maps outstanding_cents when present and null when absent', () => {
    expect(toInvoice({ ...dto, outstanding_cents: 1400 }).outstanding_cents).toBe(1400)
    expect(toInvoice(dto).outstanding_cents).toBeNull()
  })

  it('maps is_overdue from the DTO', () => {
    expect(toInvoice({ ...dto, is_overdue: true }).is_overdue).toBe(true)
    expect(toInvoice({ ...dto, is_overdue: false }).is_overdue).toBe(false)
  })
})

describe('toInvoicePage', () => {
  it('maps items and carries pagination', () => {
    const page = toInvoicePage({ items: [dto], total: 1, limit: 20, offset: 0 })
    expect(page.items).toHaveLength(1)
    expect(page.items[0]?.id).toBe(1)
    expect(page.total).toBe(1)
  })
})

describe('toInvoiceWithLines', () => {
  it('maps line items and falls back to computed line subtotal', () => {
    const withLines: InvoiceWithLinesDto = {
      ...dto,
      line_items: [
        {
          description: 'Std',
          quantity: 2,
          unit_price_cents: 1500,
          tax_rate_bps: 1000,
          line_subtotal_cents: 3000,
        },
        { description: 'NoSubtotal', quantity: 3, unit_price_cents: 1000, tax_rate_bps: 800 },
      ],
    }

    const model = toInvoiceWithLines(withLines)
    expect(model.id).toBe(1)
    expect(model.line_items).toHaveLength(2)
    expect(model.line_items[0]?.line_subtotal_cents).toBe(3000)
    expect(model.line_items[1]?.line_subtotal_cents).toBe(3000) // 3 × 1000 computed
  })

  it('defaults to an empty line item array', () => {
    const model = toInvoiceWithLines({ ...dto })
    expect(model.line_items).toEqual([])
  })
})
