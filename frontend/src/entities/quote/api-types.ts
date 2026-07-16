import type { components } from '@/shared/api/schema.gen'

export type QuoteDto = components['schemas']['Quote']
export type QuoteWithLinesDto = components['schemas']['QuoteWithLines']

export interface QuoteListDto {
  items: QuoteDto[]
  total: number
  limit: number
  offset: number
}
