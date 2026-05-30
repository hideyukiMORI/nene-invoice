import { useMutation, useQueryClient, type UseMutationResult } from '@tanstack/react-query'
import { apiClient } from '@/shared/api/client'
import type { AppError } from '@/shared/api/errors'
import { type InvoiceWithLines } from '@/entities/invoice'
import { toInvoiceWithLines } from '@/entities/invoice/mapper'
import type { InvoiceWithLinesDto } from '@/entities/invoice/api-types'
import type { QuoteWithLinesDto } from './api-types'
import { toQuoteWithLines } from './mapper'
import type { CreateQuoteInput, QuoteStatus, QuoteWithLines } from './model'
import { quoteKeys } from './query-keys'
import type { QuoteId } from './ids'
import { invoiceKeys } from '@/entities/invoice'

export function useCreateQuote(): UseMutationResult<QuoteWithLines, AppError, CreateQuoteInput> {
  const queryClient = useQueryClient()
  return useMutation<QuoteWithLines, AppError, CreateQuoteInput>({
    mutationFn: async (input) => {
      const dto = await apiClient.post<QuoteWithLinesDto>('/admin/quotes', {
        client_id: input.client_id,
        line_items: input.line_items,
        valid_until: input.valid_until,
        notes: input.notes,
      })
      return toQuoteWithLines(dto)
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: quoteKeys.lists() })
    },
  })
}

export function useChangeQuoteStatus(): UseMutationResult<
  QuoteWithLines,
  AppError,
  { id: QuoteId; status: QuoteStatus }
> {
  const queryClient = useQueryClient()
  return useMutation<QuoteWithLines, AppError, { id: QuoteId; status: QuoteStatus }>({
    mutationFn: async ({ id, status }) => {
      const dto = await apiClient.patch<QuoteWithLinesDto>(`/admin/quotes/${String(id)}`, {
        status,
      })
      return toQuoteWithLines(dto)
    },
    onSuccess: (data) => {
      queryClient.setQueryData(quoteKeys.detail(data.id), data)
      void queryClient.invalidateQueries({ queryKey: quoteKeys.lists() })
    },
  })
}

export function useConvertQuote(): UseMutationResult<InvoiceWithLines, AppError, QuoteId> {
  const queryClient = useQueryClient()
  return useMutation<InvoiceWithLines, AppError, QuoteId>({
    mutationFn: async (id) => {
      const dto = await apiClient.post<InvoiceWithLinesDto>(`/admin/quotes/${String(id)}/convert`)
      return toInvoiceWithLines(dto)
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: invoiceKeys.lists() })
      void queryClient.invalidateQueries({ queryKey: quoteKeys.lists() })
    },
  })
}
