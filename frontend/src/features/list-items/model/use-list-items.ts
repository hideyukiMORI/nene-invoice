import { useState } from 'react'
import {
  EMPTY_ITEM_FILTERS,
  useItemList,
  type Item,
  type ItemListFilters,
  type ItemSort,
  type ItemSortField,
} from '@/entities/item'

const PAGE = { limit: 100, offset: 0 }

/** Narrowed view-model for the item list — one explicit state at a time. */
export type ListItemsState =
  | { kind: 'loading' }
  | { kind: 'error'; retry: () => void }
  | { kind: 'empty' }
  | { kind: 'ready'; items: Item[] }

export interface ListItemsView {
  filters: ItemListFilters
  applyFilters: (next: ItemListFilters) => void
  resetFilters: () => void
  sort: ItemSort
  toggleSort: (field: ItemSortField) => void
  /** Total number of matching records. */
  total: number
  state: ListItemsState
}

export function useListItems(): ListItemsView {
  const [filters, setFilters] = useState<ItemListFilters>(EMPTY_ITEM_FILTERS)
  const [sort, setSort] = useState<ItemSort>({ field: null, order: 'asc' })

  const query = useItemList({ ...PAGE, filters, sort })

  const applyFilters = (next: ItemListFilters): void => {
    setFilters(next)
  }
  const resetFilters = (): void => {
    setFilters(EMPTY_ITEM_FILTERS)
  }
  const toggleSort = (field: ItemSortField): void => {
    setSort((current) =>
      current.field === field
        ? { field, order: current.order === 'asc' ? 'desc' : 'asc' }
        : { field, order: 'asc' },
    )
  }

  let state: ListItemsState
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
    state = { kind: 'ready', items: query.data.items }
  }

  const total = query.data?.total ?? 0

  return { filters, applyFilters, resetFilters, sort, toggleSort, total, state }
}
