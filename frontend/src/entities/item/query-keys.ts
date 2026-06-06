import type { ItemListFilters, ItemSort } from './model'

export interface ItemListParams {
  limit: number
  offset: number
  filters: ItemListFilters
  sort: ItemSort
}

export const itemKeys = {
  all: ['items'] as const,
  lists: () => [...itemKeys.all, 'list'] as const,
  list: (params: ItemListParams) => [...itemKeys.lists(), params] as const,
  details: () => [...itemKeys.all, 'detail'] as const,
  detail: (id: number) => [...itemKeys.details(), id] as const,
}
