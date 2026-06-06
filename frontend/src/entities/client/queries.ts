import { keepPreviousData, useQuery, type UseQueryResult } from '@tanstack/react-query'
import { apiClient } from '@/shared/api/client'
import type { AppError } from '@/shared/api/errors'
import type { ClientDto, ClientListDto } from './api-types'
import type { ClientId } from './ids'
import { toClient, toClientPage } from './mapper'
import type { Client, ClientPage } from './model'
import { clientKeys, type ClientListParams } from './query-keys'

/** GET /admin/clients — list page, mapped to models before reaching the cache. */
export function useClientList(params: ClientListParams): UseQueryResult<ClientPage, AppError> {
  return useQuery<ClientPage, AppError>({
    queryKey: clientKeys.list(params),
    // Keep the previous page while a new query (e.g. the combobox's per-keystroke
    // server search) loads, so status stays 'success' instead of flipping to
    // 'pending'. Without this, `isPending` toggling true on every keystroke
    // disabled the client picker input mid-typing (#368).
    placeholderData: keepPreviousData,
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
      const dto = await apiClient.get<ClientListDto>(`/admin/clients?${search.toString()}`)
      return toClientPage(dto)
    },
  })
}

/** GET /admin/clients/{id} — one client. */
export function useClient(id: ClientId): UseQueryResult<Client, AppError> {
  return useQuery<Client, AppError>({
    queryKey: clientKeys.detail(id),
    queryFn: async () => {
      const dto = await apiClient.get<ClientDto>(`/admin/clients/${String(id)}`)
      return toClient(dto)
    },
  })
}
