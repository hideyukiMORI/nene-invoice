import { useState } from 'react'
import {
  EMPTY_INVOICE_FILTERS,
  useInvoiceList,
  type Invoice,
  type InvoiceListFilters,
  type InvoiceSort,
  type InvoiceSortField,
} from '@/entities/invoice'

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
  | { kind: 'ready'; invoices: Invoice[] }

export interface ListInvoicesView {
  filters: InvoiceListFilters
  applyFilters: (next: InvoiceListFilters) => void
  resetFilters: () => void
  sort: InvoiceSort
  toggleSort: (field: InvoiceSortField) => void
  pagination: Pagination
  /** Total number of matching records across all pages. */
  total: number
  state: ListInvoicesState
}

export function useListInvoices(): ListInvoicesView {
  const [filters, setFilters] = useState<InvoiceListFilters>(EMPTY_INVOICE_FILTERS)
  const [sort, setSort] = useState<InvoiceSort>({ field: null, order: 'desc' })
  const [page, setPage] = useState(1)

  const query = useInvoiceList({ limit: PAGE_SIZE, offset: (page - 1) * PAGE_SIZE, filters, sort })

  const total = query.data?.total ?? 0
  const totalPages = Math.max(1, Math.ceil(total / PAGE_SIZE))

  const applyFilters = (next: InvoiceListFilters): void => {
    setPage(1)
    setFilters(next)
  }
  const resetFilters = (): void => {
    setPage(1)
    setFilters(EMPTY_INVOICE_FILTERS)
  }
  const toggleSort = (field: InvoiceSortField): void => {
    setPage(1)
    setSort((current) =>
      current.field === field
        ? { field, order: current.order === 'asc' ? 'desc' : 'asc' }
        : { field, order: 'asc' },
    )
  }

  let state: ListInvoicesState
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
    state = { kind: 'ready', invoices: query.data.items }
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
