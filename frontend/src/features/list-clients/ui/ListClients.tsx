import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useDeleteClient, type Client } from '@/entities/client'
import { useTranslation } from '@/shared/i18n'
import {
  Button,
  ConfirmDialog,
  EmptyState,
  ErrorState,
  LoadingState,
  Stack,
  Text,
} from '@/shared/ui'
import { useListClients } from '../hooks/use-list-clients'

/** Client (取引先) list screen with per-row delete (confirmed). */
export function ListClients() {
  const { t } = useTranslation()
  const state = useListClients()
  const deleteClient = useDeleteClient()
  const [pendingDelete, setPendingDelete] = useState<Client | null>(null)

  const confirmDelete = (): void => {
    if (pendingDelete === null) return
    deleteClient.mutate(pendingDelete.id, {
      onSuccess: () => {
        setPendingDelete(null)
      },
    })
  }

  return (
    <Stack gap="md">
      <div className="flex items-center justify-between">
        <Text as="h1" variant="heading-md">
          {t('admin.clients.title')}
        </Text>
        <Link to="/clients/new" className="text-body text-accent">
          {t('admin.clients.newButton')}
        </Link>
      </div>

      {state.kind === 'loading' && <LoadingState message={t('admin.clients.loading')} />}

      {state.kind === 'error' && (
        <ErrorState
          message={t('admin.clients.error')}
          retryLabel={t('common.actions.retry')}
          onRetry={state.retry}
        />
      )}

      {state.kind === 'empty' && <EmptyState message={t('admin.clients.empty')} />}

      {state.kind === 'ready' && (
        <div className="table-scroll">
          <table className="data-table">
            <thead>
              <tr>
                <th>{t('admin.clients.col.name')}</th>
                <th>{t('admin.clients.col.contact')}</th>
                <th>{t('admin.clients.col.email')}</th>
                <th>{t('admin.clients.col.registration')}</th>
                <th className="tr">{t('admin.clients.col.actions')}</th>
              </tr>
            </thead>
            <tbody>
              {state.clients.map((client) => (
                <tr key={client.id}>
                  <td data-label={t('admin.clients.col.name')}>{client.name}</td>
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
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => {
                          setPendingDelete(client)
                        }}
                      >
                        {t('admin.clients.delete.action')}
                      </Button>
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
