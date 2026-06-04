import { useState } from 'react'
import {
  EMPTY_CLIENT_FILTERS,
  useClientList,
  type Client,
  type ClientListFilters,
  type ClientSort,
  type ClientSortField,
} from '@/entities/client'

const PAGE = { limit: 100, offset: 0 }

/** Narrowed view-model for the client list — one explicit state at a time. */
export type ListClientsState =
  | { kind: 'loading' }
  | { kind: 'error'; retry: () => void }
  | { kind: 'empty' }
  | { kind: 'ready'; clients: Client[] }

export interface ListClientsView {
  filters: ClientListFilters
  applyFilters: (next: ClientListFilters) => void
  resetFilters: () => void
  sort: ClientSort
  toggleSort: (field: ClientSortField) => void
  state: ListClientsState
}

export function useListClients(): ListClientsView {
  const [filters, setFilters] = useState<ClientListFilters>(EMPTY_CLIENT_FILTERS)
  const [sort, setSort] = useState<ClientSort>({ field: null, order: 'asc' })

  const query = useClientList({ ...PAGE, filters, sort })

  const applyFilters = (next: ClientListFilters): void => {
    setFilters(next)
  }
  const resetFilters = (): void => {
    setFilters(EMPTY_CLIENT_FILTERS)
  }
  const toggleSort = (field: ClientSortField): void => {
    setSort((current) =>
      current.field === field
        ? { field, order: current.order === 'asc' ? 'desc' : 'asc' }
        : { field, order: 'asc' },
    )
  }

  let state: ListClientsState
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
    state = { kind: 'ready', clients: query.data.items }
  }

  return { filters, applyFilters, resetFilters, sort, toggleSort, state }
}
