import type { LineItem } from '@/entities/invoice'
import type { QuoteId } from './ids'

export type QuoteStatus = 'draft' | 'sent' | 'accepted' | 'rejected' | 'expired'

export interface Quote {
  id: QuoteId
  quote_number: string
  status: QuoteStatus
  client_id: number
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
