import type { components } from '@/shared/api/schema.gen'

export type ItemDto = components['schemas']['Item']

export interface ItemListDto {
  items: ItemDto[]
  total: number
  limit: number
  offset: number
}
