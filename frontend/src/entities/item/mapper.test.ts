import { describe, expect, it } from 'vitest'
import type { ItemDto } from './api-types'
import { toItem, toItemPage } from './mapper'

const dto: ItemDto = {
  id: 5,
  organization_id: 1,
  description: '保守サポート（月額）',
  default_unit_price_cents: 50000,
  default_tax_rate_bps: 1000,
}

describe('toItem', () => {
  it('brands the id and maps the defaults', () => {
    const item = toItem(dto)
    expect(item.id).toBe(5)
    expect(item.description).toBe('保守サポート（月額）')
    expect(item.default_unit_price_cents).toBe(50000)
    expect(item.default_tax_rate_bps).toBe(1000)
  })
})

describe('toItemPage', () => {
  it('maps items and pagination', () => {
    const page = toItemPage({ items: [dto], total: 1, limit: 100, offset: 0 })
    expect(page.items).toHaveLength(1)
    expect(page.items[0]?.id).toBe(5)
    expect(page.total).toBe(1)
  })
})
