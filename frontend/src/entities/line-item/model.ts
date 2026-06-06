/**
 * A line-item suggestion. Field names mirror the API (snake_case). `source` is
 * the item master (#323, authoritative) or past-document history (#315).
 * Defaults are conveniences — the operator edits price/rate per line after
 * picking; they never override the tax that applies to a sale.
 */
export interface LineItemSuggestion {
  description: string
  unit_price_cents: number
  tax_rate_bps: number
  usage_count: number
  source: 'master' | 'history'
}
