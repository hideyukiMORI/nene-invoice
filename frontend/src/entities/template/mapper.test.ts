import { describe, expect, it } from 'vitest'
import type { TemplateDto } from './api-types'
import { toTemplate, toTemplatePage, toTemplateWithLines } from './mapper'

const dto: TemplateDto = {
  id: 5,
  organization_id: 1,
  name: '月次保守テンプレート',
  notes: '毎月',
  line_items: [
    {
      id: 9,
      description: '保守サポート',
      quantity: 1,
      unit_price_cents: 50000,
      tax_rate_bps: 1000,
    },
  ],
}

describe('toTemplate', () => {
  it('brands the id and drops lines for the header view', () => {
    const template = toTemplate(dto)
    expect(template.id).toBe(5)
    expect(template.name).toBe('月次保守テンプレート')
    expect(template.notes).toBe('毎月')
    expect('line_items' in template).toBe(false)
  })
})

describe('toTemplateWithLines', () => {
  it('maps the line presets', () => {
    const template = toTemplateWithLines(dto)
    expect(template.line_items).toHaveLength(1)
    expect(template.line_items[0]?.description).toBe('保守サポート')
    expect(template.line_items[0]?.unit_price_cents).toBe(50000)
  })
})

describe('toTemplatePage', () => {
  it('maps items and pagination', () => {
    const page = toTemplatePage({ items: [dto], total: 1, limit: 50, offset: 0 })
    expect(page.items).toHaveLength(1)
    expect(page.items[0]?.id).toBe(5)
    expect(page.total).toBe(1)
  })
})
