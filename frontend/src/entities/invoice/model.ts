import type { InvoiceStatus } from './enum'
import type { InvoiceId } from './ids'

/**
 * UI read model. Field names mirror the API (snake_case, per product rule) with a
 * branded id and a narrowed status. Money stays integer cents — never floats.
 */
export interface Invoice {
  id: InvoiceId
  client_id: number
  /** Client display name (list responses only; null elsewhere). */
  client_name: string | null
  invoice_number: string | null
  status: InvoiceStatus
  /** Computed: issued/partially_paid and due_at is in the past. */
  is_overdue: boolean
  is_qualified_invoice: boolean
  issued_at: string | null
  due_at: string | null
  subtotal_cents: number
  tax_cents: number
  total_cents: number
  /** total_cents − Σ valid payments. Null on mutation responses (read-only field). */
  outstanding_cents: number | null
}

export interface InvoicePage {
  items: Invoice[]
  total: number
  limit: number
  offset: number
}

/** Applied search / filters for the admin invoice list. */
export interface InvoiceListFilters {
  q: string | null
  statuses: InvoiceStatus[]
  overdue: boolean
  due_from: string | null
  due_to: string | null
  total_min: number | null
  total_max: number | null
}

export const EMPTY_INVOICE_FILTERS: InvoiceListFilters = {
  q: null,
  statuses: [],
  overdue: false,
  due_from: null,
  due_to: null,
  total_min: null,
  total_max: null,
}

export type InvoiceSortField = 'number' | 'client' | 'status' | 'issued_at' | 'due_at' | 'total'

export interface InvoiceSort {
  field: InvoiceSortField | null
  order: 'asc' | 'desc'
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
  notes: string | null
}

/** One line as submitted on create (no derived/server fields). */
export interface LineItemInput {
  description: string
  quantity: number
  unit_price_cents: number
  tax_rate_bps: number
}

export interface CreateInvoiceInput {
  client_id: number
  line_items: LineItemInput[]
  notes: string | null
}

export interface IssueInvoiceInput {
  id: InvoiceId
  qualified: boolean
  due_at: string | null
}
