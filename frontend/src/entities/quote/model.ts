import type { LineItem } from '@/entities/invoice'
import type { QuoteId } from './ids'

export type QuoteStatus = 'draft' | 'sent' | 'accepted' | 'rejected' | 'expired'

export const QUOTE_STATUSES: readonly QuoteStatus[] = [
  'draft',
  'sent',
  'accepted',
  'rejected',
  'expired',
]

export interface Quote {
  id: QuoteId
  quote_number: string
  status: QuoteStatus
  client_id: number
  /** Client display name (list responses only; null elsewhere). */
  client_name: string | null
  issued_at: string | null
  valid_until: string | null
  subtotal_cents: number
  tax_cents: number
  total_cents: number
  notes: string | null
}

export interface QuotePage {
  items: Quote[]
  total: number
  limit: number
  offset: number
}

/** Applied search / filters for the admin quote list. */
export interface QuoteListFilters {
  q: string | null
  statuses: QuoteStatus[]
  valid_from: string | null
  valid_to: string | null
  total_min: number | null
  total_max: number | null
}

export const EMPTY_QUOTE_FILTERS: QuoteListFilters = {
  q: null,
  statuses: [],
  valid_from: null,
  valid_to: null,
  total_min: null,
  total_max: null,
}

export type QuoteSortField = 'number' | 'client' | 'status' | 'issued_at' | 'valid_until' | 'total'

export interface QuoteSort {
  field: QuoteSortField | null
  order: 'asc' | 'desc'
}

export interface QuoteWithLines extends Quote {
  line_items: LineItem[]
}

export interface CreateQuoteInput {
  client_id: number
  line_items: {
    description: string
    quantity: number
    unit_price_cents: number
    tax_rate_bps: number
  }[]
  valid_until: string | null
  notes: string | null
}
