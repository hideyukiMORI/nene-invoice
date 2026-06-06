import { useQuery, type UseQueryResult } from '@tanstack/react-query'
import { apiClient } from '@/shared/api/client'
import type { AppError } from '@/shared/api/errors'
import type { TemplateDto, TemplateListDto } from './api-types'
import type { TemplateId } from './ids'
import { toTemplatePage, toTemplateWithLines } from './mapper'
import type { TemplatePage, TemplateWithLines } from './model'
import { templateKeys, type TemplateListParams } from './query-keys'

/** GET /admin/templates — header list page, mapped before reaching the cache. */
export function useTemplateList(
  params: TemplateListParams,
): UseQueryResult<TemplatePage, AppError> {
  return useQuery<TemplatePage, AppError>({
    queryKey: templateKeys.list(params),
    queryFn: async () => {
      const search = new URLSearchParams({
        limit: String(params.limit),
        offset: String(params.offset),
      })
      const dto = await apiClient.get<TemplateListDto>(`/admin/templates?${search.toString()}`)
      return toTemplatePage(dto)
    },
  })
}

/** GET /admin/templates/{id} — one template with its line presets. */
export function useTemplate(id: TemplateId): UseQueryResult<TemplateWithLines, AppError> {
  return useQuery<TemplateWithLines, AppError>({
    queryKey: templateKeys.detail(id),
    queryFn: async () => {
      const dto = await apiClient.get<TemplateDto>(`/admin/templates/${String(id)}`)
      return toTemplateWithLines(dto)
    },
  })
}
