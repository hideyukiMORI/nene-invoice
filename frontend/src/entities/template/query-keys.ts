export interface TemplateListParams {
  limit: number
  offset: number
}

export const templateKeys = {
  all: ['templates'] as const,
  lists: () => [...templateKeys.all, 'list'] as const,
  list: (params: TemplateListParams) => [...templateKeys.lists(), params] as const,
  details: () => [...templateKeys.all, 'detail'] as const,
  detail: (id: number) => [...templateKeys.details(), id] as const,
}
