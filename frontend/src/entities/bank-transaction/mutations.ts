import { useMutation, useQueryClient, type UseMutationResult } from '@tanstack/react-query'
import { apiClient } from '@/shared/api/client'
import type { AppError } from '@/shared/api/errors'
import type { BankConfirmResultDto, BankImportResultDto, BankTransactionDto } from './api-types'
import type { BankTransactionId } from './ids'
import { toBankConfirmResult, toBankImportResult, toBankTransaction } from './mapper'
import type {
  BankConfirmResult,
  BankImportResult,
  BankTransaction,
  ConfirmBankMatchInput,
  ImportBankCsvInput,
} from './model'
import { bankTransactionKeys } from './query-keys'

/**
 * POST /admin/bank-transactions/import?preset=… — stages a bank CSV. The raw file
 * bytes are sent unchanged (Shift_JIS-safe). Both 200 (accepted) and 422 (format
 * error) resolve with the report; the caller inspects `format_error`. Invalidates
 * the lists when anything was actually staged.
 */
export function useImportBankCsv(): UseMutationResult<
  BankImportResult,
  AppError,
  ImportBankCsvInput
> {
  const queryClient = useQueryClient()

  return useMutation<BankImportResult, AppError, ImportBankCsvInput>({
    mutationFn: async (input) => {
      const search = new URLSearchParams({ preset: input.preset })
      const dto = await apiClient.postBytes<BankImportResultDto>(
        `/admin/bank-transactions/import?${search.toString()}`,
        input.file,
      )
      return toBankImportResult(dto)
    },
    onSuccess: (result) => {
      if (result.format_error === null && result.imported_count > 0) {
        void queryClient.invalidateQueries({ queryKey: bankTransactionKeys.lists() })
      }
    },
  })
}

/**
 * POST /admin/bank-transactions/{id}/confirm — records a payment against the chosen
 * invoice and posts the staged line. Invalidates the lists so the row leaves the
 * unmatched view.
 */
export function useConfirmBankMatch(): UseMutationResult<
  BankConfirmResult,
  AppError,
  ConfirmBankMatchInput
> {
  const queryClient = useQueryClient()

  return useMutation<BankConfirmResult, AppError, ConfirmBankMatchInput>({
    mutationFn: async (input) => {
      const dto = await apiClient.post<BankConfirmResultDto>(
        `/admin/bank-transactions/${String(input.id)}/confirm`,
        { invoice_id: input.invoice_id },
      )
      return toBankConfirmResult(dto)
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: bankTransactionKeys.lists() })
    },
  })
}

/** POST /admin/bank-transactions/{id}/ignore — drops a line from reconciliation. */
export function useIgnoreBankTransaction(): UseMutationResult<
  BankTransaction,
  AppError,
  BankTransactionId
> {
  const queryClient = useQueryClient()

  return useMutation<BankTransaction, AppError, BankTransactionId>({
    mutationFn: async (id) => {
      const dto = await apiClient.post<BankTransactionDto>(
        `/admin/bank-transactions/${String(id)}/ignore`,
      )
      return toBankTransaction(dto)
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: bankTransactionKeys.lists() })
    },
  })
}
