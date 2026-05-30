import { useState } from 'react'
import { useQuoteList, type Quote } from '@/entities/quote'

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
  | { kind: 'ready'; quotes: Quote[]; pagination: QuotePagination }

export function useListQuotes(): ListQuotesState {
  const [page, setPage] = useState(1)
  const query = useQuoteList({ limit: PAGE_SIZE, offset: (page - 1) * PAGE_SIZE })

  if (query.isPending) return { kind: 'loading' }
  if (query.isError) {
    return {
      kind: 'error',
      retry: () => {
        void query.refetch()
      },
    }
  }
  if (query.data.items.length === 0) return { kind: 'empty' }

  const totalPages = Math.max(1, Math.ceil(query.data.total / PAGE_SIZE))
  return {
    kind: 'ready',
    quotes: query.data.items,
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
  }
}
