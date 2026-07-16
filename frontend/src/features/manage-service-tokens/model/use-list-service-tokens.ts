import { useServiceTokenList, type ServiceToken } from '@/entities/service-token'

const PAGE = { limit: 100, offset: 0 }

export type ListServiceTokensState =
  | { kind: 'loading' }
  | { kind: 'error'; retry: () => void }
  | { kind: 'empty' }
  | { kind: 'ready'; tokens: ServiceToken[] }

export function useListServiceTokens(): ListServiceTokensState {
  const query = useServiceTokenList(PAGE)

  if (query.isPending) {
    return { kind: 'loading' }
  }
  if (query.isError) {
    return {
      kind: 'error',
      retry: () => {
        void query.refetch()
      },
    }
  }
  if (query.data.items.length === 0) {
    return { kind: 'empty' }
  }
  return { kind: 'ready', tokens: query.data.items }
}
