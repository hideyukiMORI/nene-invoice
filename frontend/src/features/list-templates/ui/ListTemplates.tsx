import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useDeleteTemplate, type Template } from '@/entities/template'
import { useTranslation } from '@/shared/i18n'
import { useRowCursor } from '@/shared/keyboard'
import {
  ConfirmDialog,
  EmptyState,
  ErrorState,
  LinkButton,
  LoadingState,
  Stack,
  Text,
} from '@/shared/ui'
import { useListTemplates } from '../model/use-list-templates'

/** Template (雛形) list screen with per-row edit / delete. */
export function ListTemplates() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const view = useListTemplates()
  const deleteTemplate = useDeleteTemplate()
  const [pendingDelete, setPendingDelete] = useState<Template | null>(null)

  const rows = view.state.kind === 'ready' ? view.state.templates : []
  const cursor = useRowCursor(rows.length, (index) => {
    const row = rows[index]
    if (row !== undefined) void navigate(`/templates/${String(row.id)}/edit`)
  })

  const confirmDelete = (): void => {
    if (pendingDelete === null) return
    deleteTemplate.mutate(pendingDelete.id, {
      onSuccess: () => {
        setPendingDelete(null)
      },
    })
  }

  return (
    <Stack gap="md">
      <div className="flex items-center justify-between">
        <Text as="h1" variant="heading-md">
          {t('admin.templates.title')}
        </Text>
        <LinkButton to="/templates/new" size="sm" aria-keyshortcuts="n">
          {t('admin.templates.newButton')}
        </LinkButton>
      </div>

      {view.state.kind === 'loading' && <LoadingState message={t('admin.templates.loading')} />}

      {view.state.kind === 'error' && (
        <ErrorState
          message={t('admin.templates.error')}
          retryLabel={t('common.actions.retry')}
          onRetry={view.state.retry}
        />
      )}

      {view.state.kind === 'empty' && <EmptyState message={t('admin.templates.empty')} />}

      {view.state.kind === 'ready' && (
        <div className="table-scroll">
          <table className="data-table">
            <thead>
              <tr>
                <th>{t('admin.templates.col.name')}</th>
                <th>{t('admin.templates.col.notes')}</th>
                <th className="tr">{t('admin.templates.col.actions')}</th>
              </tr>
            </thead>
            <tbody>
              {view.state.templates.map((template, index) => (
                <tr
                  key={template.id}
                  data-kbd-row={index}
                  className={cursor === index ? 'is-cursor' : undefined}
                >
                  <td data-label={t('admin.templates.col.name')}>{template.name}</td>
                  <td data-label={t('admin.templates.col.notes')}>{template.notes ?? '—'}</td>
                  <td className="tr" data-label={t('admin.templates.col.actions')}>
                    <Stack direction="row" gap="sm" className="justify-end">
                      <Link
                        to={`/templates/${String(template.id)}/edit`}
                        className="text-body text-accent"
                      >
                        {t('admin.templates.editButton')}
                      </Link>
                      <button
                        type="button"
                        className="cursor-pointer border-0 bg-transparent p-0 text-body text-danger"
                        onClick={() => {
                          setPendingDelete(template)
                        }}
                      >
                        {t('admin.templates.delete.action')}
                      </button>
                    </Stack>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {deleteTemplate.isError && (
        <Text variant="muted" role="alert">
          {t('admin.templates.delete.error')}
        </Text>
      )}

      {pendingDelete !== null && (
        <ConfirmDialog
          title={t('admin.templates.delete.title')}
          message={t('admin.templates.delete.message', { name: pendingDelete.name })}
          confirmLabel={t('admin.templates.delete.confirm')}
          cancelLabel={t('common.actions.cancel')}
          pending={deleteTemplate.isPending}
          onConfirm={confirmDelete}
          onCancel={() => {
            setPendingDelete(null)
          }}
        />
      )}
    </Stack>
  )
}
