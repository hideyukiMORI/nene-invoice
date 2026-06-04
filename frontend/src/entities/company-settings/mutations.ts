import { useMutation, useQueryClient, type UseMutationResult } from '@tanstack/react-query'
import { apiClient } from '@/shared/api/client'
import type { AppError } from '@/shared/api/errors'
import type { CompanySettingsDto } from './api-types'
import { toCompanySettings } from './mapper'
import type { CompanySettings, UpdateCompanySettingsInput } from './model'
import { companySettingsKeys } from './query-keys'

export function useUpdateCompanySettings(): UseMutationResult<
  CompanySettings,
  AppError,
  UpdateCompanySettingsInput
> {
  const queryClient = useQueryClient()
  return useMutation<CompanySettings, AppError, UpdateCompanySettingsInput>({
    mutationFn: async (input) => {
      const dto = await apiClient.put<CompanySettingsDto>('/admin/company-settings', {
        legal_name: input.legal_name,
        address: input.address,
        phone: input.phone,
        email: input.email,
        registration_number: input.registration_number,
        bank_name: input.bank_name,
        bank_branch: input.bank_branch,
        account_type: input.account_type,
        account_number: input.account_number,
        default_quote_validity_days: input.default_quote_validity_days,
        default_payment_closing_day: input.default_payment_closing_day,
        default_payment_month_offset: input.default_payment_month_offset,
        default_payment_pay_day: input.default_payment_pay_day,
      })
      return toCompanySettings(dto)
    },
    onSuccess: (data) => {
      queryClient.setQueryData(companySettingsKeys.detail(), data)
    },
  })
}
