import { useTemplateList, type Template } from '@/entities/template'

const PAGE = { limit: 100, offset: 0 }

/** Narrowed view-model for the template list — one explicit state at a time. */
export type ListTemplatesState =
  | { kind: 'loading' }
  | { kind: 'error'; retry: () => void }
  | { kind: 'empty' }
  | { kind: 'ready'; templates: Template[] }

export interface ListTemplatesView {
  total: number
  state: ListTemplatesState
}

export function useListTemplates(): ListTemplatesView {
  const query = useTemplateList(PAGE)

  let state: ListTemplatesState
  if (query.isPending) {
    state = { kind: 'loading' }
  } else if (query.isError) {
    state = {
      kind: 'error',
      retry: () => {
        void query.refetch()
      },
    }
  } else if (query.data.items.length === 0) {
    state = { kind: 'empty' }
  } else {
    state = { kind: 'ready', templates: query.data.items }
  }

  return { total: query.data?.total ?? 0, state }
}
