import { useMutation, useQueryClient, type UseMutationResult } from '@tanstack/react-query'
import { apiClient } from '@/shared/api/client'
import type { AppError } from '@/shared/api/errors'
import type { TemplateDto } from './api-types'
import type { TemplateId } from './ids'
import { toTemplateWithLines } from './mapper'
import type { CreateTemplateInput, TemplateWithLines, UpdateTemplateInput } from './model'
import { templateKeys } from './query-keys'

/** POST /admin/templates — creates a template; invalidates the lists on success. */
export function useCreateTemplate(): UseMutationResult<
  TemplateWithLines,
  AppError,
  CreateTemplateInput
> {
  const queryClient = useQueryClient()

  return useMutation<TemplateWithLines, AppError, CreateTemplateInput>({
    mutationFn: async (input) => {
      const dto = await apiClient.post<TemplateDto>('/admin/templates', {
        name: input.name,
        notes: input.notes,
        line_items: input.line_items,
      })
      return toTemplateWithLines(dto)
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: templateKeys.lists() })
    },
  })
}

/** PATCH /admin/templates/{id} — updates a template; invalidates lists + detail. */
export function useUpdateTemplate(): UseMutationResult<
  TemplateWithLines,
  AppError,
  UpdateTemplateInput
> {
  const queryClient = useQueryClient()

  return useMutation<TemplateWithLines, AppError, UpdateTemplateInput>({
    mutationFn: async (input) => {
      const dto = await apiClient.patch<TemplateDto>(`/admin/templates/${String(input.id)}`, {
        name: input.name,
        notes: input.notes,
        line_items: input.line_items,
      })
      return toTemplateWithLines(dto)
    },
    onSuccess: (template) => {
      void queryClient.invalidateQueries({ queryKey: templateKeys.lists() })
      void queryClient.invalidateQueries({ queryKey: templateKeys.detail(template.id) })
    },
  })
}

/** DELETE /admin/templates/{id} — deletes a template; invalidates the lists. */
export function useDeleteTemplate(): UseMutationResult<TemplateId, AppError, TemplateId> {
  const queryClient = useQueryClient()

  return useMutation<TemplateId, AppError, TemplateId>({
    mutationFn: async (id) => {
      await apiClient.delete(`/admin/templates/${String(id)}`)
      return id
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: templateKeys.lists() })
    },
  })
}
