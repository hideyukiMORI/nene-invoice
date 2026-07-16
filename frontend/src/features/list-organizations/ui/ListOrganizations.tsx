import { useState } from 'react'
import { useDeleteOrganization, type Organization } from '@/entities/organization'
import { useTranslation } from '@/shared/i18n'
import {
  Badge,
  ConfirmDialog,
  EmptyState,
  ErrorState,
  LinkButton,
  LoadingState,
  Stack,
  Text,
} from '@/shared/ui'
import { useListOrganizations } from '../model/use-list-organizations'

/** Superadmin organization (tenant) list with per-row delete (confirmed). */
export function ListOrganizations() {
  const { t } = useTranslation()
  const state = useListOrganizations()
  const deleteOrganization = useDeleteOrganization()
  const [pendingDelete, setPendingDelete] = useState<Organization | null>(null)

  const confirmDelete = (): void => {
    if (pendingDelete === null) return
    deleteOrganization.mutate(pendingDelete.id, {
      onSuccess: () => {
        setPendingDelete(null)
      },
    })
  }

  return (
    <Stack gap="md">
      <div className="flex items-center justify-between">
        <Text as="h1" variant="heading-md">
          {t('admin.organizations.title')}
        </Text>
        <LinkButton to="/organizations/new" size="sm" aria-keyshortcuts="n">
          {t('admin.organizations.newButton')}
        </LinkButton>
      </div>

      {state.kind === 'loading' && <LoadingState message={t('admin.organizations.loading')} />}

      {state.kind === 'error' && (
        <ErrorState
          message={t('admin.organizations.error')}
          retryLabel={t('common.actions.retry')}
          onRetry={state.retry}
        />
      )}

      {state.kind === 'empty' && <EmptyState message={t('admin.organizations.empty')} />}

      {state.kind === 'ready' && (
        <div className="table-scroll">
          <table className="data-table">
            <thead>
              <tr>
                <th>{t('admin.organizations.col.name')}</th>
                <th>{t('admin.organizations.col.slug')}</th>
                <th>{t('admin.organizations.col.plan')}</th>
                <th>{t('admin.organizations.col.status')}</th>
                <th className="tr">{t('admin.organizations.col.actions')}</th>
              </tr>
            </thead>
            <tbody>
              {state.organizations.map((organization) => (
                <tr key={organization.id}>
                  <td data-label={t('admin.organizations.col.name')}>{organization.name}</td>
                  <td data-label={t('admin.organizations.col.slug')}>{organization.slug}</td>
                  <td data-label={t('admin.organizations.col.plan')}>{organization.plan ?? '—'}</td>
                  <td data-label={t('admin.organizations.col.status')}>
                    <Badge tone={organization.is_active ? 'ok' : 'neutral'}>
                      {organization.is_active
                        ? t('admin.organizations.status.active')
                        : t('admin.organizations.status.inactive')}
                    </Badge>
                  </td>
                  <td className="tr" data-label={t('admin.organizations.col.actions')}>
                    <Stack direction="row" gap="sm" className="justify-end">
                      <button
                        type="button"
                        className="cursor-pointer border-0 bg-transparent p-0 text-body text-danger"
                        onClick={() => {
                          setPendingDelete(organization)
                        }}
                      >
                        {t('admin.organizations.delete.action')}
                      </button>
                    </Stack>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {deleteOrganization.isError && (
        <Text variant="muted" role="alert">
          {t('admin.organizations.delete.error')}
        </Text>
      )}

      {pendingDelete !== null && (
        <ConfirmDialog
          title={t('admin.organizations.delete.title')}
          message={t('admin.organizations.delete.message', { name: pendingDelete.name })}
          confirmLabel={t('admin.organizations.delete.confirm')}
          cancelLabel={t('common.actions.cancel')}
          pending={deleteOrganization.isPending}
          onConfirm={confirmDelete}
          onCancel={() => {
            setPendingDelete(null)
          }}
        />
      )}
    </Stack>
  )
}
