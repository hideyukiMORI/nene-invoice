import { useMutation, useQueryClient, type UseMutationResult } from '@tanstack/react-query'
import { apiClient } from '@/shared/api/client'
import type { AppError } from '@/shared/api/errors'
import type { RecurringInvoiceWithLinesDto } from './api-types'
import type { RecurringInvoiceId } from './ids'
import { toRecurringInvoiceWithLines } from './mapper'
import type {
  CreateRecurringInvoiceInput,
  RecurringInvoiceWithLines,
  UpdateRecurringInvoiceInput,
} from './model'
import { recurringInvoiceKeys } from './query-keys'

/**
 * POST /admin/recurring-invoices — creates a schedule (totals computed by the API).
 * Invalidates the recurring-invoice lists on success so the new schedule appears.
 */
export function useCreateRecurringInvoice(): UseMutationResult<
  RecurringInvoiceWithLines,
  AppError,
  CreateRecurringInvoiceInput
> {
  const queryClient = useQueryClient()

  return useMutation<RecurringInvoiceWithLines, AppError, CreateRecurringInvoiceInput>({
    mutationFn: async (input) => {
      const dto = await apiClient.post<RecurringInvoiceWithLinesDto>('/admin/recurring-invoices', {
        client_id: input.client_id,
        name: input.name,
        frequency: input.frequency,
        first_run_on: input.first_run_on,
        line_items: input.line_items.map((line) => ({
          description: line.description,
          quantity: line.quantity,
          unit_price_cents: line.unit_price_cents,
          tax_rate_bps: line.tax_rate_bps,
        })),
        is_active: input.is_active,
        notes: input.notes,
      })
      return toRecurringInvoiceWithLines(dto)
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: recurringInvoiceKeys.lists() })
    },
  })
}

/**
 * PATCH /admin/recurring-invoices/{id} — replaces the line template and recomputes
 * totals. Invalidates the lists and detail so the new state shows.
 */
export function useUpdateRecurringInvoice(): UseMutationResult<
  RecurringInvoiceWithLines,
  AppError,
  UpdateRecurringInvoiceInput
> {
  const queryClient = useQueryClient()

  return useMutation<RecurringInvoiceWithLines, AppError, UpdateRecurringInvoiceInput>({
    mutationFn: async (input) => {
      const dto = await apiClient.patch<RecurringInvoiceWithLinesDto>(
        `/admin/recurring-invoices/${String(input.id)}`,
        {
          client_id: input.client_id,
          name: input.name,
          frequency: input.frequency,
          next_run_on: input.next_run_on,
          line_items: input.line_items.map((line) => ({
            description: line.description,
            quantity: line.quantity,
            unit_price_cents: line.unit_price_cents,
            tax_rate_bps: line.tax_rate_bps,
          })),
          is_active: input.is_active,
          notes: input.notes,
        },
      )
      return toRecurringInvoiceWithLines(dto)
    },
    onSuccess: (recurringInvoice) => {
      void queryClient.invalidateQueries({ queryKey: recurringInvoiceKeys.lists() })
      void queryClient.invalidateQueries({
        queryKey: recurringInvoiceKeys.detail(recurringInvoice.id),
      })
    },
  })
}

/** DELETE /admin/recurring-invoices/{id} — soft-deletes a schedule; invalidates the lists. */
export function useDeleteRecurringInvoice(): UseMutationResult<
  RecurringInvoiceId,
  AppError,
  RecurringInvoiceId
> {
  const queryClient = useQueryClient()

  return useMutation<RecurringInvoiceId, AppError, RecurringInvoiceId>({
    mutationFn: async (id) => {
      await apiClient.delete(`/admin/recurring-invoices/${String(id)}`)
      return id
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: recurringInvoiceKeys.lists() })
    },
  })
}
