import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useDeleteRecurringInvoice, type RecurringInvoice } from '@/entities/recurring-invoice'
import { useTranslation } from '@/shared/i18n'
import { useRowCursor } from '@/shared/keyboard'
import { formatCalendarDate } from '@/shared/lib/format-date'
import { formatYen } from '@/shared/lib/format-money'
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
import { useListRecurringInvoices } from '../hooks/use-list-recurring-invoices'

/** Recurring-invoice (継続請求) list screen with an active badge and per-row delete. */
export function ListRecurringInvoices() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const view = useListRecurringInvoices()
  const deleteRecurringInvoice = useDeleteRecurringInvoice()
  const [pendingDelete, setPendingDelete] = useState<RecurringInvoice | null>(null)

  const rows = view.state.kind === 'ready' ? view.state.recurringInvoices : []
  const cursor = useRowCursor(rows.length, (index) => {
    const row = rows[index]
    if (row !== undefined) void navigate(`/recurring/${String(row.id)}/edit`)
  })

  const confirmDelete = (): void => {
    if (pendingDelete === null) return
    deleteRecurringInvoice.mutate(pendingDelete.id, {
      onSuccess: () => {
        setPendingDelete(null)
      },
    })
  }

  return (
    <Stack gap="md">
      <div className="flex items-center justify-between">
        <Text as="h1" variant="heading-md">
          {t('admin.recurring.title')}
        </Text>
        <LinkButton to="/recurring/new" size="sm" aria-keyshortcuts="n">
          {t('admin.recurring.newButton')}
        </LinkButton>
      </div>

      {view.state.kind === 'loading' && <LoadingState message={t('admin.recurring.loading')} />}

      {view.state.kind === 'error' && (
        <ErrorState
          message={t('admin.recurring.error')}
          retryLabel={t('common.actions.retry')}
          onRetry={view.state.retry}
        />
      )}

      {view.state.kind === 'empty' && <EmptyState message={t('admin.recurring.empty')} />}

      {view.state.kind === 'ready' && (
        <div className="table-scroll">
          <table className="data-table">
            <thead>
              <tr>
                <th>{t('admin.recurring.col.name')}</th>
                <th>{t('admin.recurring.col.client')}</th>
                <th>{t('admin.recurring.col.frequency')}</th>
                <th>{t('admin.recurring.col.nextRun')}</th>
                <th className="tr">{t('admin.recurring.col.total')}</th>
                <th>{t('admin.recurring.col.status')}</th>
                <th className="tr">{t('admin.recurring.col.actions')}</th>
              </tr>
            </thead>
            <tbody>
              {view.state.recurringInvoices.map((recurring, index) => (
                <tr
                  key={recurring.id}
                  data-kbd-row={index}
                  className={cursor === index ? 'is-cursor' : undefined}
                >
                  <td data-label={t('admin.recurring.col.name')}>
                    <Link
                      to={`/recurring/${String(recurring.id)}/edit`}
                      className="text-body text-accent"
                    >
                      {recurring.name}
                    </Link>
                  </td>
                  <td data-label={t('admin.recurring.col.client')}>
                    {recurring.client_name ?? `#${String(recurring.client_id)}`}
                  </td>
                  <td data-label={t('admin.recurring.col.frequency')}>
                    {t(`admin.recurring.frequency.${recurring.frequency}`)}
                  </td>
                  <td className="num" data-label={t('admin.recurring.col.nextRun')}>
                    {formatCalendarDate(recurring.next_run_on)}
                  </td>
                  <td className="tr num" data-label={t('admin.recurring.col.total')}>
                    {formatYen(recurring.total_cents)}
                  </td>
                  <td data-label={t('admin.recurring.col.status')}>
                    <Badge tone={recurring.is_active ? 'ok' : 'neutral'}>
                      {t(
                        recurring.is_active
                          ? 'admin.recurring.status.active'
                          : 'admin.recurring.status.inactive',
                      )}
                    </Badge>
                  </td>
                  <td className="tr" data-label={t('admin.recurring.col.actions')}>
                    <Stack direction="row" gap="sm" className="justify-end">
                      <Link
                        to={`/recurring/${String(recurring.id)}/edit`}
                        className="text-body text-accent"
                      >
                        {t('admin.recurring.editButton')}
                      </Link>
                      <button
                        type="button"
                        className="cursor-pointer border-0 bg-transparent p-0 text-body text-danger"
                        onClick={() => {
                          setPendingDelete(recurring)
                        }}
                      >
                        {t('admin.recurring.delete.action')}
                      </button>
                    </Stack>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {deleteRecurringInvoice.isError && (
        <Text variant="muted" role="alert">
          {t('admin.recurring.delete.error')}
        </Text>
      )}

      {pendingDelete !== null && (
        <ConfirmDialog
          title={t('admin.recurring.delete.title')}
          message={t('admin.recurring.delete.message', { name: pendingDelete.name })}
          confirmLabel={t('admin.recurring.delete.confirm')}
          cancelLabel={t('common.actions.cancel')}
          pending={deleteRecurringInvoice.isPending}
          onConfirm={confirmDelete}
          onCancel={() => {
            setPendingDelete(null)
          }}
        />
      )}
    </Stack>
  )
}
