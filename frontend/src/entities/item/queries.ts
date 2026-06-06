import { useQuery, type UseQueryResult } from '@tanstack/react-query'
import { apiClient } from '@/shared/api/client'
import type { AppError } from '@/shared/api/errors'
import type { ItemDto, ItemListDto } from './api-types'
import type { ItemId } from './ids'
import { toItem, toItemPage } from './mapper'
import type { Item, ItemPage } from './model'
import { itemKeys, type ItemListParams } from './query-keys'

/** GET /admin/items — list page, mapped to models before reaching the cache. */
export function useItemList(params: ItemListParams): UseQueryResult<ItemPage, AppError> {
  return useQuery<ItemPage, AppError>({
    queryKey: itemKeys.list(params),
    queryFn: async () => {
      const search = new URLSearchParams({
        limit: String(params.limit),
        offset: String(params.offset),
      })
      if (params.filters.q !== null) search.set('q', params.filters.q)
      if (params.sort.field !== null) {
        search.set('sort', params.sort.field)
        search.set('order', params.sort.order)
      }
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
