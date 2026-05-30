import { useState } from 'react'
import { useInvoiceList, type Invoice } from '@/entities/invoice'

const PAGE_SIZE = 20

export interface Pagination {
  page: number
  totalPages: number
  hasPrev: boolean
  hasNext: boolean
  prevPage: () => void
  nextPage: () => void
}

/** Narrowed view-model for the invoice list — one explicit state at a time. */
export type ListInvoicesState =
  | { kind: 'loading' }
  | { kind: 'error'; retry: () => void }
  | { kind: 'empty' }
  | { kind: 'ready'; invoices: Invoice[]; pagination: Pagination }

export function useListInvoices(): ListInvoicesState {
  const [page, setPage] = useState(1)
  const query = useInvoiceList({ limit: PAGE_SIZE, offset: (page - 1) * PAGE_SIZE })

  if (query.isPending) {
    return { kind: 'loading' }
  }
  if (query.isError) {
    return {
      kind: 'error',
      retry: () => {
        void query.refetch()
      },
    }
  }
  if (query.data.items.length === 0) {
    return { kind: 'empty' }
  }

  const totalPages = Math.max(1, Math.ceil(query.data.total / PAGE_SIZE))
  return {
    kind: 'ready',
    invoices: query.data.items,
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
