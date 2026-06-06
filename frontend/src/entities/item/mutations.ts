import { useMutation, useQueryClient, type UseMutationResult } from '@tanstack/react-query'
import { apiClient } from '@/shared/api/client'
import type { AppError } from '@/shared/api/errors'
import type { ItemDto } from './api-types'
import type { ItemId } from './ids'
import { toItem } from './mapper'
import type { Item, CreateItemInput, UpdateItemInput } from './model'
import { itemKeys } from './query-keys'

/** POST /admin/items — creates an item; invalidates the item lists on success. */
export function useCreateItem(): UseMutationResult<Item, AppError, CreateItemInput> {
  const queryClient = useQueryClient()

  return useMutation<Item, AppError, CreateItemInput>({
    mutationFn: async (input) => {
      const dto = await apiClient.post<ItemDto>('/admin/items', {
        description: input.description,
        default_unit_price_cents: input.default_unit_price_cents,
        default_tax_rate_bps: input.default_tax_rate_bps,
      })
      return toItem(dto)
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: itemKeys.lists() })
    },
  })
}

/** PATCH /admin/items/{id} — updates an item; invalidates the lists and detail. */
export function useUpdateItem(): UseMutationResult<Item, AppError, UpdateItemInput> {
  const queryClient = useQueryClient()

  return useMutation<Item, AppError, UpdateItemInput>({
    mutationFn: async (input) => {
      const dto = await apiClient.patch<ItemDto>(`/admin/items/${String(input.id)}`, {
        description: input.description,
        default_unit_price_cents: input.default_unit_price_cents,
        default_tax_rate_bps: input.default_tax_rate_bps,
      })
      return toItem(dto)
    },
    onSuccess: (item) => {
      void queryClient.invalidateQueries({ queryKey: itemKeys.lists() })
      void queryClient.invalidateQueries({ queryKey: itemKeys.detail(item.id) })
    },
  })
}

/** DELETE /admin/items/{id} — soft-deletes an item; invalidates the lists. */
export function useDeleteItem(): UseMutationResult<ItemId, AppError, ItemId> {
  const queryClient = useQueryClient()

  return useMutation<ItemId, AppError, ItemId>({
    mutationFn: async (id) => {
      await apiClient.delete(`/admin/items/${String(id)}`)
      return id
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: itemKeys.lists() })
    },
  })
}
