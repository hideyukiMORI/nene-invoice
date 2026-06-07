import { useQuery, type UseQueryResult } from '@tanstack/react-query'
import { apiClient } from '@/shared/api/client'
import type { AppError } from '@/shared/api/errors'
import type { ItemDto, ItemListDto } from './api-types'
import type { ItemId } from './ids'
import { toItem, toItemPage } from './mapper'
import type { Item, ItemListFilters, ItemPage, ItemSort } from './model'
import { itemKeys, type ItemListParams } from './query-keys'

/**
 * Serializes the admin list filter + sort into query params. Shared by the list
 * query and the CSV export so the export mirrors what the list shows.
 */
export function buildItemListSearch(filters: ItemListFilters, sort: ItemSort): URLSearchParams {
  const search = new URLSearchParams()
  if (filters.q !== null) search.set('q', filters.q)
  if (sort.field !== null) {
    search.set('sort', sort.field)
    search.set('order', sort.order)
  }
  return search
}

/** GET /admin/items — list page, mapped to models before reaching the cache. */
export function useItemList(params: ItemListParams): UseQueryResult<ItemPage, AppError> {
  return useQuery<ItemPage, AppError>({
    queryKey: itemKeys.list(params),
    queryFn: async () => {
      const search = buildItemListSearch(params.filters, params.sort)
      search.set('limit', String(params.limit))
      search.set('offset', String(params.offset))
      const dto = await apiClient.get<ItemListDto>(`/admin/items?${search.toString()}`)
      return toItemPage(dto)
    },
  })
}

/** GET /admin/items/{id} — one item. */
export function useItem(id: ItemId): UseQueryResult<Item, AppError> {
  return useQuery<Item, AppError>({
    queryKey: itemKeys.detail(id),
    queryFn: async () => {
      const dto = await apiClient.get<ItemDto>(`/admin/items/${String(id)}`)
      return toItem(dto)
    },
  })
}
