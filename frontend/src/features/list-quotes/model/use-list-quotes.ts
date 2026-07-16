import { useState } from 'react'
import {
  EMPTY_QUOTE_FILTERS,
  useQuoteList,
  type Quote,
  type QuoteListFilters,
  type QuoteSort,
  type QuoteSortField,
} from '@/entities/quote'

const PAGE_SIZE = 20

export interface QuotePagination {
  page: number
  totalPages: number
  hasPrev: boolean
  hasNext: boolean
  prevPage: () => void
  nextPage: () => void
}

export type ListQuotesState =
  | { kind: 'loading' }
  | { kind: 'error'; retry: () => void }
  | { kind: 'empty' }
  | { kind: 'ready'; quotes: Quote[] }

export interface ListQuotesView {
  filters: QuoteListFilters
  applyFilters: (next: QuoteListFilters) => void
  resetFilters: () => void
  sort: QuoteSort
  toggleSort: (field: QuoteSortField) => void
  pagination: QuotePagination
  /** Total number of matching records across all pages. */
  total: number
  state: ListQuotesState
}

export function useListQuotes(): ListQuotesView {
  const [filters, setFilters] = useState<QuoteListFilters>(EMPTY_QUOTE_FILTERS)
  const [sort, setSort] = useState<QuoteSort>({ field: null, order: 'desc' })
  const [page, setPage] = useState(1)

  const query = useQuoteList({ limit: PAGE_SIZE, offset: (page - 1) * PAGE_SIZE, filters, sort })

  const total = query.data?.total ?? 0
  const totalPages = Math.max(1, Math.ceil(total / PAGE_SIZE))

  const applyFilters = (next: QuoteListFilters): void => {
    setPage(1)
    setFilters(next)
  }
  const resetFilters = (): void => {
    setPage(1)
    setFilters(EMPTY_QUOTE_FILTERS)
  }
  const toggleSort = (field: QuoteSortField): void => {
    setPage(1)
    setSort((current) =>
      current.field === field
        ? { field, order: current.order === 'asc' ? 'desc' : 'asc' }
        : { field, order: 'asc' },
    )
  }

  let state: ListQuotesState
  if (query.isPending) {
    state = { kind: 'loading' }
  } else if (query.isError) {
    state = {
      kind: 'error',
      retry: () => {
        void query.refetch()
      },
    }
  } else if (query.data.items.length === 0) {
    state = { kind: 'empty' }
  } else {
    state = { kind: 'ready', quotes: query.data.items }
  }

  return {
    filters,
    applyFilters,
    resetFilters,
    sort,
    toggleSort,
    pagination: {
      page,
      totalPages,
      hasPrev: page > 1,
      hasNext: page < totalPages,
      prevPage: () => {
        setPage((p) => Math.max(1, p - 1))
      },
      nextPage: () => {
        setPage((p) => Math.min(totalPages, p + 1))
      },
    },
    total,
    state,
  }
}
