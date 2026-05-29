import { useClientList, type Client } from '@/entities/client'

const PAGE = { limit: 100, offset: 0 }

/** Narrowed view-model for the client list — one explicit state at a time. */
export type ListClientsState =
  | { kind: 'loading' }
  | { kind: 'error'; retry: () => void }
  | { kind: 'empty' }
  | { kind: 'ready'; clients: Client[] }

export function useListClients(): ListClientsState {
  const query = useClientList(PAGE)

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
  return { kind: 'ready', clients: query.data.items }
}
