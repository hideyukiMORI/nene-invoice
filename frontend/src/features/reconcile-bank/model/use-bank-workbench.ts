import { useState } from 'react'
import {
  useBankTransactionList,
  type BankTransaction,
  type BankTransactionStatus,
} from '@/entities/bank-transaction'

const PAGE_SIZE = 50

export interface BankWorkbenchPagination {
  page: number
  totalPages: number
  hasPrev: boolean
  hasNext: boolean
  prevPage: () => void
  nextPage: () => void
}

/** Narrowed view-model for the staged-lines table — one explicit state at a time. */
export type BankWorkbenchState =
  | { kind: 'loading' }
  | { kind: 'error'; retry: () => void }
  | { kind: 'empty' }
  | { kind: 'ready'; transactions: BankTransaction[] }

export interface BankWorkbenchView {
  /** Active status filter (null = any). Defaults to 未消込 — the actionable set. */
  status: BankTransactionStatus | null
  setStatus: (status: BankTransactionStatus | null) => void
  pagination: BankWorkbenchPagination
  /** Total number of matching records across all pages. */
  total: number
  state: BankWorkbenchState
}

export function useBankWorkbench(): BankWorkbenchView {
  const [status, setStatusState] = useState<BankTransactionStatus | null>('unmatched')
  const [page, setPage] = useState(1)

  const query = useBankTransactionList({
    status,
    limit: PAGE_SIZE,
    offset: (page - 1) * PAGE_SIZE,
  })

  const total = query.data?.total ?? 0
  const totalPages = Math.max(1, Math.ceil(total / PAGE_SIZE))

  const setStatus = (next: BankTransactionStatus | null): void => {
    setPage(1)
    setStatusState(next)
  }

  let state: BankWorkbenchState
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
    state = { kind: 'ready', transactions: query.data.items }
  }

  return {
    status,
    setStatus,
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
