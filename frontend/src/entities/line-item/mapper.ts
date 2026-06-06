import type { LineItemSuggestionDto } from './api-types'
import type { LineItemSuggestion } from './model'

export function toLineItemSuggestion(dto: LineItemSuggestionDto): LineItemSuggestion {
  return {
    description: dto.description,
    unit_price_cents: dto.unit_price_cents,
    tax_rate_bps: dto.tax_rate_bps,
    usage_count: dto.usage_count,
    source: dto.source,
  }
}
