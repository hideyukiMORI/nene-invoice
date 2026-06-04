import type { LineItem } from '@/entities/invoice'
import type { QuoteDto, QuoteListDto, QuoteWithLinesDto } from './api-types'
import { toQuoteId } from './ids'
import type { Quote, QuotePage, QuoteWithLines } from './model'

export function toQuote(dto: QuoteDto): Quote {
  return {
    id: toQuoteId(dto.id),
    quote_number: dto.quote_number,
    status: dto.status,
    client_id: dto.client_id,
    client_name: dto.client_name ?? null,
    issued_at: dto.issued_at ?? null,
    valid_until: dto.valid_until ?? null,
    subtotal_cents: dto.subtotal_cents,
    tax_cents: dto.tax_cents,
    total_cents: dto.total_cents,
    notes: dto.notes ?? null,
  }
}

export function toQuotePage(dto: QuoteListDto): QuotePage {
  return { items: dto.items.map(toQuote), total: dto.total, limit: dto.limit, offset: dto.offset }
}

function toLineItem(dto: {
  description: string
  quantity: number
  unit_price_cents: number
  tax_rate_bps: number
  line_subtotal_cents?: number
}): LineItem {
  return {
    description: dto.description,
    quantity: dto.quantity,
    unit_price_cents: dto.unit_price_cents,
    tax_rate_bps: dto.tax_rate_bps,
    line_subtotal_cents: dto.line_subtotal_cents ?? dto.quantity * dto.unit_price_cents,
  }
}

export function toQuoteWithLines(dto: QuoteWithLinesDto): QuoteWithLines {
  return {
    ...toQuote(dto),
    line_items: (dto.line_items ?? []).map(toLineItem),
  }
}
