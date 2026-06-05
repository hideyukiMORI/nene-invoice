import { useState, type ReactNode, type SyntheticEvent } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import {
  EMPTY_CLIENT_FILTERS,
  useDeleteClient,
  type Client,
  type ClientListFilters,
  type ClientSortField,
} from '@/entities/client'
import { useTranslation } from '@/shared/i18n'
import { KbdHint, useRowCursor } from '@/shared/keyboard'
import {
  ConfirmDialog,
  EmptyState,
  ErrorState,
  Field,
  FilterBar,
  Input,
  LinkButton,
  LoadingState,
  SortableTh,
  Stack,
  Text,
} from '@/shared/ui'
import { useListClients } from '../hooks/use-list-clients'

const trimmedOrNull = (value: string): string | null => (value.trim() === '' ? null : value.trim())

/** Client (取引先) list screen with search / sort and per-row delete. */
export function ListClients() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const view = useListClients()
  const deleteClient = useDeleteClient()
  const [pendingDelete, setPendingDelete] = useState<Client | null>(null)
  const [draft, setDraft] = useState<ClientListFilters>(EMPTY_CLIENT_FILTERS)

  const rows = view.state.kind === 'ready' ? view.state.clients : []
  const cursor = useRowCursor(rows.length, (index) => {
    const row = rows[index]
    if (row !== undefined) void navigate(`/clients/${String(row.id)}/edit`)
  })

  const confirmDelete = (): void => {
    if (pendingDelete === null) return
    deleteClient.mutate(pendingDelete.id, {
      onSuccess: () => {
        setPendingDelete(null)
      },
    })
  }

  const onSubmit = (event: SyntheticEvent): void => {
    event.preventDefault()
    view.applyFilters(draft)
  }
  const onReset = (): void => {
    setDraft(EMPTY_CLIENT_FILTERS)
    view.resetFilters()
  }

  const sortableTh = (field: ClientSortField, label: string): ReactNode => (
    <SortableTh
      label={label}
      active={view.sort.field === field}
      order={view.sort.order}
      onToggle={() => {
        view.toggleSort(field)
      }}
    />
  )

  return (
    <Stack gap="md">
      <div className="flex items-center justify-between">
        <Text as="h1" variant="heading-md">
          {t('admin.clients.title')}
        </Text>
        <LinkButton to="/clients/new" size="sm" aria-keyshortcuts="n">
          {t('admin.clients.newButton')}
        </LinkButton>
      </div>

      <FilterBar count={view.total} onSubmit={onSubmit} onReset={onReset}>
        <Field id="client-q" label={t('admin.clients.filter.search')}>
          <div className="field-kbd">
            <Input
              id="client-q"
              data-kbd="search"
              aria-keyshortcuts="/"
              className="pr-9"
              value={draft.q ?? ''}
              placeholder={t('admin.clients.filter.searchPlaceholder')}
              onChange={(e) => {
                setDraft({ ...draft, q: trimmedOrNull(e.target.value) })
              }}
            />
            <KbdHint>/</KbdHint>
          </div>
        </Field>
      </FilterBar>

      {view.state.kind === 'loading' && <LoadingState message={t('admin.clients.loading')} />}

      {view.state.kind === 'error' && (
        <ErrorState
          message={t('admin.clients.error')}
          retryLabel={t('common.actions.retry')}
          onRetry={view.state.retry}
        />
      )}

      {view.state.kind === 'empty' && <EmptyState message={t('admin.clients.empty')} />}

      {view.state.kind === 'ready' && (
        <div className="table-scroll">
          <table className="data-table">
            <thead>
              <tr>
                {sortableTh('name', t('admin.clients.col.name'))}
                {sortableTh('contact', t('admin.clients.col.contact'))}
                {sortableTh('email', t('admin.clients.col.email'))}
                {sortableTh('registration', t('admin.clients.col.registration'))}
                <th className="tr">{t('admin.clients.col.actions')}</th>
              </tr>
            </thead>
            <tbody>
              {view.state.clients.map((client, index) => (
                <tr
                  key={client.id}
                  data-kbd-row={index}
                  className={cursor === index ? 'is-cursor' : undefined}
                >
                  <td data-label={t('admin.clients.col.name')}>
                    {client.name}
                    {client.name_kana !== null && (
                      <span className="block text-caption text-fg-muted">{client.name_kana}</span>
                    )}
                  </td>
                  <td data-label={t('admin.clients.col.contact')}>{client.contact_name ?? '—'}</td>
                  <td data-label={t('admin.clients.col.email')}>{client.email ?? '—'}</td>
                  <td className="num" data-label={t('admin.clients.col.registration')}>
                    {client.registration_number ?? '—'}
                  </td>
                  <td className="tr" data-label={t('admin.clients.col.actions')}>
                    <Stack direction="row" gap="sm" className="justify-end">
                      <Link
                        to={`/clients/${String(client.id)}/edit`}
                        className="text-body text-accent"
                      >
                        {t('admin.clients.editButton')}
                      </Link>
                      <button
                        type="button"
                        className="cursor-pointer border-0 bg-transparent p-0 text-body text-danger"
                        onClick={() => {
                          setPendingDelete(client)
                        }}
                      >
                        {t('admin.clients.delete.action')}
                      </button>
                    </Stack>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {deleteClient.isError && (
        <Text variant="muted" role="alert">
          {t('admin.clients.delete.error')}
        </Text>
      )}

      {pendingDelete !== null && (
        <ConfirmDialog
          title={t('admin.clients.delete.title')}
          message={t('admin.clients.delete.message', { name: pendingDelete.name })}
          confirmLabel={t('admin.clients.delete.confirm')}
          cancelLabel={t('common.actions.cancel')}
          pending={deleteClient.isPending}
          onConfirm={confirmDelete}
          onCancel={() => {
            setPendingDelete(null)
          }}
        />
      )}
    </Stack>
  )
}
