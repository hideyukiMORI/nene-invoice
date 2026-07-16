import { useState } from 'react'
import {
  EMPTY_AUDIT_LOG_FILTERS,
  useAuditLogList,
  type AuditLog,
  type AuditLogFilters,
} from '@/entities/audit'

const PAGE_SIZE = 20

export type AuditLogListState =
  | { kind: 'loading' }
  | { kind: 'error'; retry: () => void }
  | { kind: 'empty' }
  | { kind: 'ready'; logs: AuditLog[] }

export interface AuditLogListView {
  filters: AuditLogFilters
  applyFilters: (next: AuditLogFilters) => void
  resetFilters: () => void
  page: number
  pageCount: number
  total: number
  canPrev: boolean
  canNext: boolean
  goPrev: () => void
  goNext: () => void
  state: AuditLogListState
}

/** Owns the audit-log filter + pagination state and derives a view state. */
export function useListAuditLogs(): AuditLogListView {
  const [filters, setFilters] = useState<AuditLogFilters>(EMPTY_AUDIT_LOG_FILTERS)
  const [offset, setOffset] = useState(0)

  const query = useAuditLogList({ ...filters, limit: PAGE_SIZE, offset })

  const total = query.data?.total ?? 0
  const pageCount = Math.max(1, Math.ceil(total / PAGE_SIZE))
  const page = Math.floor(offset / PAGE_SIZE) + 1

  const applyFilters = (next: AuditLogFilters): void => {
    setOffset(0)
    setFilters(next)
  }

  const resetFilters = (): void => {
    setOffset(0)
    setFilters(EMPTY_AUDIT_LOG_FILTERS)
  }

  const goPrev = (): void => {
    setOffset((current) => Math.max(0, current - PAGE_SIZE))
  }

  const goNext = (): void => {
    setOffset((current) => (current + PAGE_SIZE < total ? current + PAGE_SIZE : current))
  }

  let state: AuditLogListState
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
    state = { kind: 'ready', logs: query.data.items }
  }

  return {
    filters,
    applyFilters,
    resetFilters,
    page,
    pageCount,
    total,
    canPrev: offset > 0,
    canNext: offset + PAGE_SIZE < total,
    goPrev,
    goNext,
    state,
  }
}
