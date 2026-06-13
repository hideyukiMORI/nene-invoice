import { useMutation, useQueryClient, type UseMutationResult } from '@tanstack/react-query'
import { apiClient } from '@/shared/api/client'
import type { AppError } from '@/shared/api/errors'
import type { CompanySeal, CompanySealStateDto } from './model'
import { companySealKeys } from './query-keys'

/** PUT /admin/company-settings/seal — uploads a base64 PNG seal. */
export function useUploadCompanySeal(): UseMutationResult<CompanySeal, AppError, string> {
  const queryClient = useQueryClient()
  return useMutation<CompanySeal, AppError, string>({
    mutationFn: async (imageBase64) => {
      const dto = await apiClient.put<CompanySealStateDto>('/admin/company-settings/seal', {
        image_base64: imageBase64,
      })
      return { has_seal: dto.has_seal, image_base64: dto.has_seal ? imageBase64 : null }
    },
    onSuccess: (data) => {
      queryClient.setQueryData(companySealKeys.detail(), data)
    },
  })
}

/** DELETE /admin/company-settings/seal — removes the seal. */
export function useDeleteCompanySeal(): UseMutationResult<CompanySeal, AppError, void> {
  const queryClient = useQueryClient()
  return useMutation<CompanySeal, AppError>({
    mutationFn: async () => {
      await apiClient.delete<CompanySealStateDto>('/admin/company-settings/seal')
      return { has_seal: false, image_base64: null }
    },
    onSuccess: (data) => {
      queryClient.setQueryData(companySealKeys.detail(), data)
    },
  })
}
