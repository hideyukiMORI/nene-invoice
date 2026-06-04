import type { QuoteListFilters, QuoteSort } from './model'

export interface QuoteListParams {
  limit: number
  offset: number
  filters: QuoteListFilters
  sort: QuoteSort
}

export const quoteKeys = {
  all: ['quotes'] as const,
  lists: () => [...quoteKeys.all, 'list'] as const,
  list: (params: QuoteListParams) => [...quoteKeys.lists(), params] as const,
  details: () => [...quoteKeys.all, 'detail'] as const,
  detail: (id: number) => [...quoteKeys.details(), id] as const,
}
