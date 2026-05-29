import { describe, expect, it } from 'vitest'
import type { InvoiceDto } from './api-types'
import { toInvoice, toInvoicePage } from './mapper'

const dto: InvoiceDto = {
  id: 1,
  organization_id: 1,
  client_id: 5,
  status: 'issued',
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
})

describe('toInvoicePage', () => {
  it('maps items and carries pagination', () => {
    const page = toInvoicePage({ items: [dto], total: 1, limit: 20, offset: 0 })
    expect(page.items).toHaveLength(1)
    expect(page.items[0]?.id).toBe(1)
    expect(page.total).toBe(1)
  })
})
