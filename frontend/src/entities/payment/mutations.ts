import { useMutation, useQueryClient, type UseMutationResult } from '@tanstack/react-query'
import { apiClient } from '@/shared/api/client'
import type { AppError } from '@/shared/api/errors'
import type { RecordPaymentResponseDto } from './api-types'
import { toRecordPaymentResult } from './mapper'
import type { RecordPaymentInput, RecordPaymentResult } from './model'
import { paymentKeys } from './query-keys'

/**
 * POST /admin/invoices/{id}/payments — records a payment. Invalidates this
 * invoice's payment list. The invoice's own state (status / paid totals) is
 * refreshed by the calling feature, which owns the cross-entity invalidation.
 */
export function useRecordPayment(): UseMutationResult<
  RecordPaymentResult,
  AppError,
  RecordPaymentInput
> {
  const queryClient = useQueryClient()

  return useMutation<RecordPaymentResult, AppError, RecordPaymentInput>({
    mutationFn: async (input) => {
      const dto = await apiClient.post<RecordPaymentResponseDto>(
        `/admin/invoices/${String(input.invoice_id)}/payments`,
        { amount_cents: input.amount_cents, method: input.method, note: input.note },
      )
      return toRecordPaymentResult(dto)
    },
    onSuccess: (_result, input) => {
      void queryClient.invalidateQueries({ queryKey: paymentKeys.forInvoice(input.invoice_id) })
    },
  })
}
