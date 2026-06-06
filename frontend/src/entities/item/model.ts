import type { ItemId } from './ids'

/**
 * UI read model for an item-master row (品目). Field names mirror the API
 * (snake_case). Defaults seed document lines; the operator edits price/rate per
 * line afterwards — they never override the tax that applies to a sale.
 */
export interface Item {
  id: ItemId
  description: string
  default_unit_price_cents: number
  default_tax_rate_bps: number
}

export interface ItemPage {
  items: Item[]
  total: number
  limit: number
  offset: number
}

/** Applied search for the admin item list. */
export interface ItemListFilters {
  q: string | null
}

export const EMPTY_ITEM_FILTERS: ItemListFilters = { q: null }

export type ItemSortField = 'description' | 'unit_price' | 'tax_rate'

export interface ItemSort {
  field: ItemSortField | null
  order: 'asc' | 'desc'
}

export interface CreateItemInput {
  description: string
  default_unit_price_cents: number
  default_tax_rate_bps: number
}

export interface UpdateItemInput extends CreateItemInput {
  id: ItemId
}
