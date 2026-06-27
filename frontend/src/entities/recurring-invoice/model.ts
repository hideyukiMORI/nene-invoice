import type { RecurringFrequency } from './enum'
import type { RecurringInvoiceId } from './ids'

/**
 * UI read model for a recurring-billing schedule (継続請求). Field names mirror the
 * API (snake_case, per product rule) with a branded id and a narrowed frequency.
 * Money stays integer cents — never floats.
 */
export interface RecurringInvoice {
  id: RecurringInvoiceId
  client_id: number
  /** Client display name (list responses only; null elsewhere). */
  client_name: string | null
  name: string
  frequency: RecurringFrequency
  subtotal_cents: number
  tax_cents: number
  total_cents: number
  /** Next run calendar date (YYYY-MM-DD). */
  next_run_on: string
  /** Last run calendar date (YYYY-MM-DD), or null if never run. */
  last_run_on: string | null
  is_active: boolean
  notes: string | null
}

export interface RecurringInvoicePage {
  items: RecurringInvoice[]
  total: number
  limit: number
  offset: number
}

/** One line of the recurring template. Money is integer cents; tax rate is bps. */
export interface LineItem {
  description: string
  quantity: number
  unit_price_cents: number
  tax_rate_bps: number
  line_subtotal_cents: number
}

export interface RecurringInvoiceWithLines extends RecurringInvoice {
  line_items: LineItem[]
}

/** One line as submitted on create/update (no derived/server fields). */
export interface LineItemInput {
  description: string
  quantity: number
  unit_price_cents: number
  tax_rate_bps: number
}

export interface CreateRecurringInvoiceInput {
  client_id: number
  name: string
  frequency: RecurringFrequency
  /** First run calendar date (YYYY-MM-DD). */
  first_run_on: string
  line_items: LineItemInput[]
  is_active: boolean
  notes: string | null
}

export interface UpdateRecurringInvoiceInput {
  id: RecurringInvoiceId
  client_id: number
  name: string
  frequency: RecurringFrequency
  /** Next run calendar date (YYYY-MM-DD). */
  next_run_on: string
  line_items: LineItemInput[]
  is_active: boolean
  notes: string | null
}
