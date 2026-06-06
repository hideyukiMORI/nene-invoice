import type { components } from '@/shared/api/schema.gen'

export type TemplateDto = components['schemas']['Template']

export interface TemplateListDto {
  items: TemplateDto[]
  total: number
  limit: number
  offset: number
}
