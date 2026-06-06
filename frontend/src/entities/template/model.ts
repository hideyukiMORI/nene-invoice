import type { TemplateId } from './ids'

/** One line preset on a template. Money is integer cents; tax rate is bps. */
export interface TemplateLine {
  description: string
  quantity: number
  unit_price_cents: number
  tax_rate_bps: number
}

/** Template header (list rows carry no line presets). */
export interface Template {
  id: TemplateId
  name: string
  notes: string | null
}

export interface TemplateWithLines extends Template {
  line_items: TemplateLine[]
}

export interface TemplatePage {
  items: Template[]
  total: number
  limit: number
  offset: number
}

export interface CreateTemplateInput {
  name: string
  notes: string | null
  line_items: TemplateLine[]
}

export interface UpdateTemplateInput extends CreateTemplateInput {
  id: TemplateId
}
