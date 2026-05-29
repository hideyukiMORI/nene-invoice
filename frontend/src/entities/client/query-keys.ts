export interface ClientListParams {
  limit: number
  offset: number
}

export const clientKeys = {
  all: ['clients'] as const,
  lists: () => [...clientKeys.all, 'list'] as const,
  list: (params: ClientListParams) => [...clientKeys.lists(), params] as const,
}
