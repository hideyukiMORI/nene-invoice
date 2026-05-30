export const quoteKeys = {
  all: ['quotes'] as const,
  lists: () => [...quoteKeys.all, 'list'] as const,
  list: (params: { limit: number; offset: number }) => [...quoteKeys.lists(), params] as const,
  details: () => [...quoteKeys.all, 'detail'] as const,
  detail: (id: number) => [...quoteKeys.details(), id] as const,
}
