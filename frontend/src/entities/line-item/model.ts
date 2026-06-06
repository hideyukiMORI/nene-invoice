/**
 * A history-based line-item suggestion (#315). Field names mirror the API
 * (snake_case). Defaults are conveniences — the operator edits price/rate per
 * line after picking; they never override the tax that applies to a sale.
 */
export interface LineItemSuggestion {
  description: string
  unit_price_cents: number
  tax_rate_bps: number
  usage_count: number
}
