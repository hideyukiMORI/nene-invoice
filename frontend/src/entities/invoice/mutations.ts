import { useMutation, useQueryClient, type UseMutationResult } from '@tanstack/react-query'
import { apiClient } from '@/shared/api/client'
import type { AppError } from '@/shared/api/errors'
import type { InvoiceWithLinesDto } from './api-types'
import { toInvoiceWithLines } from './mapper'
import type { CreateInvoiceInput, InvoiceWithLines } from './model'
import { invoiceKeys } from './query-keys'

/**
 * POST /admin/invoices — creates a draft invoice (tax/totals computed by the API).
 * Invalidates the invoice lists on success so the new draft appears.
 */
export function useCreateInvoice(): UseMutationResult<
  InvoiceWithLines,
  AppError,
  CreateInvoiceInput
> {
  const queryClient = useQueryClient()

  return useMutation<InvoiceWithLines, AppError, CreateInvoiceInput>({
    mutationFn: async (input) => {
      const dto = await apiClient.post<InvoiceWithLinesDto>('/admin/invoices', {
        client_id: input.client_id,
        line_items: input.line_items.map((line) => ({
          description: line.description,
          quantity: line.quantity,
          unit_price_cents: line.unit_price_cents,
          tax_rate_bps: line.tax_rate_bps,
        })),
        notes: input.notes,
      })
      return toInvoiceWithLines(dto)
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: invoiceKeys.lists() })
    },
  })
}
