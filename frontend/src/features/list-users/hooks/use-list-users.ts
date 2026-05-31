import { useUserList, type User } from '@/entities/user'

const PAGE = { limit: 100, offset: 0 }

export type ListUsersState =
  | { kind: 'loading' }
  | { kind: 'error'; retry: () => void }
  | { kind: 'empty' }
  | { kind: 'ready'; users: User[] }

export function useListUsers(): ListUsersState {
  const query = useUserList(PAGE)

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
  return { kind: 'ready', users: query.data.items }
}
