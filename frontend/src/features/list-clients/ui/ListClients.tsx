import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useDeleteClient, type Client } from '@/entities/client'
import { useTranslation } from '@/shared/i18n'
import { Button, ConfirmDialog, EmptyState, ErrorState, LoadingState, Stack, Text } from '@/shared/ui'
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

      {state.kind === 'loading' && (
        <LoadingState message={t('admin.clients.loading')} />
      )}

      {state.kind === 'error' && (
        <ErrorState
          message={t('admin.clients.error')}
          retryLabel={t('common.actions.retry')}
          onRetry={state.retry}
        />
      )}

      {state.kind === 'empty' && <EmptyState message={t('admin.clients.empty')} />}

      {state.kind === 'ready' && (
        <table className="w-full border-collapse text-body">
          <thead>
            <tr className="border-b border-border text-left">
              <th className="py-stack-sm pr-inline-md font-medium">
                {t('admin.clients.col.name')}
              </th>
              <th className="py-stack-sm pr-inline-md font-medium">
                {t('admin.clients.col.contact')}
              </th>
              <th className="py-stack-sm pr-inline-md font-medium">
                {t('admin.clients.col.email')}
              </th>
              <th className="py-stack-sm pr-inline-md font-medium">
                {t('admin.clients.col.registration')}
              </th>
              <th className="py-stack-sm text-right font-medium">
                {t('admin.clients.col.actions')}
              </th>
            </tr>
          </thead>
          <tbody>
            {state.clients.map((client) => (
              <tr key={client.id} className="border-b border-border">
                <td className="py-stack-sm pr-inline-md">{client.name}</td>
                <td className="py-stack-sm pr-inline-md">{client.contact_name ?? '—'}</td>
                <td className="py-stack-sm pr-inline-md">{client.email ?? '—'}</td>
                <td className="py-stack-sm pr-inline-md">{client.registration_number ?? '—'}</td>
                <td className="py-stack-sm text-right">
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
