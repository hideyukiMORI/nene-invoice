import { useOrganizationList, type Organization } from '@/entities/organization'

const PAGE = { limit: 100, offset: 0 }

export type ListOrganizationsState =
  | { kind: 'loading' }
  | { kind: 'error'; retry: () => void }
  | { kind: 'empty' }
  | { kind: 'ready'; organizations: Organization[] }

export function useListOrganizations(): ListOrganizationsState {
  const query = useOrganizationList(PAGE)

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
  return { kind: 'ready', organizations: query.data.items }
}
