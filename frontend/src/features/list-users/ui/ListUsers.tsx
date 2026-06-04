import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useDeleteUser, type User, type UserRole, type UserStatus } from '@/entities/user'
import { useTranslation } from '@/shared/i18n'
import { KbdHint, useRowCursor } from '@/shared/keyboard'
import {
  Badge,
  type BadgeTone,
  ConfirmDialog,
  EmptyState,
  ErrorState,
  LinkButton,
  LoadingState,
  Stack,
  Text,
} from '@/shared/ui'
import { useListUsers } from '../hooks/use-list-users'

const ROLE_TONE: Record<UserRole, BadgeTone> = {
  superadmin: 'brand',
  admin: 'info',
  member: 'neutral',
  viewer: 'neutral',
}

const STATUS_TONE: Record<UserStatus, BadgeTone> = {
  active: 'ok',
  invited: 'warn',
}

/** User list screen with per-row delete (confirmed). */
export function ListUsers() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const state = useListUsers()
  const deleteUser = useDeleteUser()
  const [pendingDelete, setPendingDelete] = useState<User | null>(null)

  const rows = state.kind === 'ready' ? state.users : []
  const cursor = useRowCursor(rows.length, (index) => {
    const row = rows[index]
    if (row !== undefined) void navigate(`/users/${String(row.id)}/edit`)
  })

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
        <LinkButton to="/users/new" size="sm" aria-keyshortcuts="n">
          {t('admin.users.newButton')}
          <KbdHint variant="solid">n</KbdHint>
        </LinkButton>
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
        <div className="table-scroll">
          <table className="data-table">
            <thead>
              <tr>
                <th>{t('admin.users.col.email')}</th>
                <th>{t('admin.users.col.role')}</th>
                <th>{t('admin.users.col.status')}</th>
                <th className="tr">{t('admin.users.col.actions')}</th>
              </tr>
            </thead>
            <tbody>
              {state.users.map((user, index) => (
                <tr
                  key={user.id}
                  data-kbd-row={index}
                  className={cursor === index ? 'is-cursor' : undefined}
                >
                  <td data-label={t('admin.users.col.email')}>{user.email}</td>
                  <td data-label={t('admin.users.col.role')}>
                    <Badge tone={ROLE_TONE[user.role]}>{t(`admin.users.role.${user.role}`)}</Badge>
                  </td>
                  <td data-label={t('admin.users.col.status')}>
                    <Badge tone={STATUS_TONE[user.status]}>
                      {t(`admin.users.status.${user.status}`)}
                    </Badge>
                  </td>
                  <td className="tr" data-label={t('admin.users.col.actions')}>
                    <Stack direction="row" gap="sm" className="justify-end">
                      <Link to={`/users/${String(user.id)}/edit`} className="text-body text-accent">
                        {t('admin.users.editButton')}
                      </Link>
                      <button
                        type="button"
                        className="cursor-pointer border-0 bg-transparent p-0 text-body text-danger"
                        onClick={() => {
                          setPendingDelete(user)
                        }}
                      >
                        {t('admin.users.delete.action')}
                      </button>
                    </Stack>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
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
