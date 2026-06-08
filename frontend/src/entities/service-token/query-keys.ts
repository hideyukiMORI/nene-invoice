export interface ServiceTokenListParams {
  limit: number
  offset: number
}

export const serviceTokenKeys = {
  all: ['service-tokens'] as const,
  lists: () => [...serviceTokenKeys.all, 'list'] as const,
  list: (params: ServiceTokenListParams) => [...serviceTokenKeys.lists(), params] as const,
}
