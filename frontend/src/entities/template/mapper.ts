import type { TemplateDto, TemplateListDto } from './api-types'
import { toTemplateId } from './ids'
import type { Template, TemplatePage, TemplateWithLines } from './model'

export function toTemplate(dto: TemplateDto): Template {
  return {
    id: toTemplateId(dto.id),
    name: dto.name,
    notes: dto.notes ?? null,
  }
}

export function toTemplateWithLines(dto: TemplateDto): TemplateWithLines {
  return {
    ...toTemplate(dto),
    line_items: dto.line_items.map((l) => ({
      description: l.description,
      quantity: l.quantity,
      unit_price_cents: l.unit_price_cents,
      tax_rate_bps: l.tax_rate_bps,
    })),
  }
}

export function toTemplatePage(dto: TemplateListDto): TemplatePage {
  return {
    items: dto.items.map(toTemplate),
    total: dto.total,
    limit: dto.limit,
    offset: dto.offset,
  }
}
