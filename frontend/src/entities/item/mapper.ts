import type { ItemDto, ItemListDto } from './api-types'
import { toItemId } from './ids'
import type { Item, ItemPage } from './model'

export function toItem(dto: ItemDto): Item {
  return {
    id: toItemId(dto.id),
    description: dto.description,
    default_unit_price_cents: dto.default_unit_price_cents,
    default_tax_rate_bps: dto.default_tax_rate_bps,
  }
}

export function toItemPage(dto: ItemListDto): ItemPage {
  return {
    items: dto.items.map(toItem),
    total: dto.total,
    limit: dto.limit,
    offset: dto.offset,
  }
}
