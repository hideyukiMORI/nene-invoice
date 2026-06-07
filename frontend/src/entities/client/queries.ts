import { keepPreviousData, useQuery, type UseQueryResult } from '@tanstack/react-query'
import { apiClient } from '@/shared/api/client'
import type { AppError } from '@/shared/api/errors'
import type { ClientDto, ClientListDto } from './api-types'
import type { ClientId } from './ids'
import { toClient, toClientPage } from './mapper'
import type { Client, ClientListFilters, ClientPage, ClientSort } from './model'
import { clientKeys, type ClientListParams } from './query-keys'

/**
 * Serializes the admin list filter + sort into query params. Shared by the list
 * query and the CSV export so the export mirrors what the list shows.
 */
export function buildClientListSearch(
  filters: ClientListFilters,
  sort: ClientSort,
): URLSearchParams {
  const search = new URLSearchParams()
  if (filters.q !== null) search.set('q', filters.q)
  if (sort.field !== null) {
    search.set('sort', sort.field)
    search.set('order', sort.order)
  }
  return search
}

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
      const search = buildClientListSearch(params.filters, params.sort)
      search.set('limit', String(params.limit))
      search.set('offset', String(params.offset))
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
