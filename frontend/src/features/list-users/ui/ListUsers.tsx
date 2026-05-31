import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useDeleteUser, type User } from '@/entities/user'
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
import { useListUsers } from '../hooks/use-list-users'

/** User list screen with per-row delete (confirmed). */
export function ListUsers() {
  const { t } = useTranslation()
  const state = useListUsers()
  const deleteUser = useDeleteUser()
  const [pendingDelete, setPendingDelete] = useState<User | null>(null)

  const confirmDelete = (): void => {
    if (pendingDelete === null) return
    deleteUser.mutate(pendingDelete.id, {
      onSuccess: () => {
        setPendingDelete(null)
      },
    })
  }

  return (
    <Stack gap="md">
      <div className="flex items-center justify-between">
        <Text as="h1" variant="heading-md">
          {t('admin.users.title')}
        </Text>
        <Link to="/users/new" className="text-body text-accent">
          {t('admin.users.newButton')}
        </Link>
      </div>

      {state.kind === 'loading' && <LoadingState message={t('admin.users.loading')} />}

      {state.kind === 'error' && (
        <ErrorState
          message={t('admin.users.error')}
          retryLabel={t('common.actions.retry')}
          onRetry={state.retry}
        />
      )}

      {state.kind === 'empty' && <EmptyState message={t('admin.users.empty')} />}

      {state.kind === 'ready' && (
        <table className="w-full border-collapse text-body">
          <thead>
            <tr className="border-b border-border text-left">
              <th className="py-stack-sm pr-inline-md font-medium">{t('admin.users.col.email')}</th>
              <th className="py-stack-sm pr-inline-md font-medium">{t('admin.users.col.role')}</th>
              <th className="py-stack-sm pr-inline-md font-medium">
                {t('admin.users.col.status')}
              </th>
              <th className="py-stack-sm text-right font-medium">{t('admin.users.col.actions')}</th>
            </tr>
          </thead>
          <tbody>
            {state.users.map((user) => (
              <tr key={user.id} className="border-b border-border">
                <td className="py-stack-sm pr-inline-md">{user.email}</td>
                <td className="py-stack-sm pr-inline-md">{t(`admin.users.role.${user.role}`)}</td>
                <td className="py-stack-sm pr-inline-md">
                  {t(`admin.users.status.${user.status}`)}
                </td>
                <td className="py-stack-sm text-right">
                  <Stack direction="row" gap="sm" className="justify-end">
                    <Link to={`/users/${String(user.id)}/edit`} className="text-body text-accent">
                      {t('admin.users.editButton')}
                    </Link>
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={() => {
                        setPendingDelete(user)
                      }}
                    >
                      {t('admin.users.delete.action')}
                    </Button>
                  </Stack>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}

      {deleteUser.isError && (
        <Text variant="muted" role="alert">
          {t('admin.users.delete.error')}
        </Text>
      )}

      {pendingDelete !== null && (
        <ConfirmDialog
          title={t('admin.users.delete.title')}
          message={t('admin.users.delete.message', { email: pendingDelete.email })}
          confirmLabel={t('admin.users.delete.confirm')}
          cancelLabel={t('common.actions.cancel')}
          pending={deleteUser.isPending}
          onConfirm={confirmDelete}
          onCancel={() => {
            setPendingDelete(null)
          }}
        />
      )}
    </Stack>
  )
}
