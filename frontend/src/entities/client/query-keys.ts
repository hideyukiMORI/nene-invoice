import type { ClientListFilters, ClientSort } from './model'

export interface ClientListParams {
  limit: number
  offset: number
  filters: ClientListFilters
  sort: ClientSort
}

export const clientKeys = {
  all: ['clients'] as const,
  lists: () => [...clientKeys.all, 'list'] as const,
  list: (params: ClientListParams) => [...clientKeys.lists(), params] as const,
  details: () => [...clientKeys.all, 'detail'] as const,
  detail: (id: number) => [...clientKeys.details(), id] as const,
}
