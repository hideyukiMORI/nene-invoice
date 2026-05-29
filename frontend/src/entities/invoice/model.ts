import type { InvoiceStatus } from './enum'
import type { InvoiceId } from './ids'

/**
 * UI read model. Field names mirror the API (snake_case, per product rule) with a
 * branded id and a narrowed status. Money stays integer cents — never floats.
 */
export interface Invoice {
  id: InvoiceId
  invoice_number: string | null
  status: InvoiceStatus
  client_id: number
  is_qualified_invoice: boolean
  issued_at: string | null
  due_at: string | null
  subtotal_cents: number
  tax_cents: number
  total_cents: number
}

export interface InvoicePage {
  items: Invoice[]
  total: number
  limit: number
  offset: number
}

/** One invoice line. Money is integer cents; tax rate is basis points. */
export interface LineItem {
  description: string
  quantity: number
  unit_price_cents: number
  tax_rate_bps: number
  line_subtotal_cents: number
}

export interface InvoiceWithLines extends Invoice {
  line_items: LineItem[]
}
