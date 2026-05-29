import { useQuery, type UseQueryResult } from '@tanstack/react-query'
import { apiClient } from '@/shared/api/client'
import type { AppError } from '@/shared/api/errors'
import type { PaymentListDto } from './api-types'
import { toPaymentList } from './mapper'
import type { PaymentList } from './model'
import { paymentKeys } from './query-keys'

/** GET /admin/invoices/{id}/payments — payments recorded against the invoice. */
export function usePaymentList(invoiceId: number): UseQueryResult<PaymentList, AppError> {
  return useQuery<PaymentList, AppError>({
    queryKey: paymentKeys.forInvoice(invoiceId),
    queryFn: async () => {
      const dto = await apiClient.get<PaymentListDto>(
        `/admin/invoices/${String(invoiceId)}/payments`,
      )
      return toPaymentList(dto)
    },
  })
}
