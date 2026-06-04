import { useState, type ReactNode, type SyntheticEvent } from 'react'
import { Link } from 'react-router-dom'
import {
  EMPTY_INVOICE_FILTERS,
  INVOICE_STATUSES,
  invoiceStatusTone,
  useExportInvoicesCsv,
  useExportPaymentsCsv,
  type InvoiceListFilters,
  type InvoiceSortField,
} from '@/entities/invoice'
import { useTranslation } from '@/shared/i18n'
import { formatYen } from '@/shared/lib/format-money'
import {
  Badge,
  Button,
  EmptyState,
  ErrorState,
  Field,
  Input,
  LinkButton,
  LoadingState,
  Select,
  Stack,
  Text,
} from '@/shared/ui'
import { useListInvoices } from '../hooks/use-list-invoices'

const toNullableInt = (value: string): number | null => {
  const n = Number.parseInt(value, 10)
  return Number.isNaN(n) ? null : n
}
const trimmedOrNull = (value: string): string | null => (value.trim() === '' ? null : value.trim())

/** Invoice list screen with search / filter / sort. */
export function ListInvoices() {
  const { t } = useTranslation()
  const view = useListInvoices()
  const exportInvoices = useExportInvoicesCsv()
  const exportPayments = useExportPaymentsCsv()

  const [draft, setDraft] = useState<InvoiceListFilters>(EMPTY_INVOICE_FILTERS)

  const onSubmit = (event: SyntheticEvent): void => {
    event.preventDefault()
    view.applyFilters(draft)
  }
  const onReset = (): void => {
    setDraft(EMPTY_INVOICE_FILTERS)
    view.resetFilters()
  }

  const sortIndicator = (field: InvoiceSortField): string =>
    view.sort.field === field ? (view.sort.order === 'asc' ? ' ▲' : ' ▼') : ''

  const sortableTh = (field: InvoiceSortField, label: string, right = false): ReactNode => (
    <th className={right ? 'tr' : undefined}>
      <button
        type="button"
        className="th-sort"
        onClick={() => {
          view.toggleSort(field)
        }}
      >
        {label}
        {sortIndicator(field)}
      </button>
    </th>
  )

  return (
    <Stack gap="md">
      <div className="flex items-center justify-between">
        <Text as="h1" variant="heading-md">
          {t('admin.invoices.title')}
        </Text>
        <Stack direction="row" gap="sm">
          <Button
            variant="ghost"
            size="sm"
            onClick={exportInvoices.download}
            disabled={exportInvoices.isDownloading}
          >
            {exportInvoices.isDownloading
              ? t('admin.invoices.export.downloading')
              : t('admin.invoices.export.invoices')}
          </Button>
          <Button
            variant="ghost"
            size="sm"
            onClick={exportPayments.download}
            disabled={exportPayments.isDownloading}
          >
            {exportPayments.isDownloading
              ? t('admin.invoices.export.downloading')
              : t('admin.invoices.export.payments')}
          </Button>
          <LinkButton to="/invoices/new" size="sm">
            {t('admin.invoices.newButton')}
          </LinkButton>
        </Stack>
      </div>

      {(exportInvoices.errorMessage !== null || exportPayments.errorMessage !== null) && (
        <Text variant="muted" role="alert">
          {exportInvoices.errorMessage ?? exportPayments.errorMessage}
        </Text>
      )}

      <form onSubmit={onSubmit}>
        <Stack gap="sm">
          <div className="audit-filters">
            <Field id="inv-q" label={t('admin.invoices.filter.search')}>
              <Input
                id="inv-q"
                value={draft.q ?? ''}
                placeholder={t('admin.invoices.filter.searchPlaceholder')}
                onChange={(e) => {
                  setDraft({ ...draft, q: trimmedOrNull(e.target.value) })
                }}
              />
            </Field>
            <Field id="inv-status" label={t('admin.invoices.filter.status')}>
              <Select
                id="inv-status"
                value={draft.statuses[0] ?? ''}
                onChange={(e) => {
                  const v = e.target.value
                  setDraft({
                    ...draft,
                    statuses: v === '' ? [] : [v as (typeof INVOICE_STATUSES)[number]],
                  })
                }}
              >
                <option value="">{t('admin.invoices.filter.statusAny')}</option>
                {INVOICE_STATUSES.map((s) => (
                  <option key={s} value={s}>
                    {t(`admin.invoices.status.${s}`)}
                  </option>
                ))}
              </Select>
            </Field>
            <Field id="inv-due-from" label={t('admin.invoices.filter.dueFrom')}>
              <Input
                id="inv-due-from"
                type="date"
                value={draft.due_from ?? ''}
                onChange={(e) => {
                  setDraft({ ...draft, due_from: trimmedOrNull(e.target.value) })
                }}
              />
            </Field>
            <Field id="inv-due-to" label={t('admin.invoices.filter.dueTo')}>
              <Input
                id="inv-due-to"
                type="date"
                value={draft.due_to ?? ''}
                onChange={(e) => {
                  setDraft({ ...draft, due_to: trimmedOrNull(e.target.value) })
                }}
              />
            </Field>
            <Field id="inv-total-min" label={t('admin.invoices.filter.totalMin')}>
              <Input
                id="inv-total-min"
                type="number"
                inputMode="numeric"
                value={draft.total_min ?? ''}
                onChange={(e) => {
                  setDraft({ ...draft, total_min: toNullableInt(e.target.value) })
                }}
              />
            </Field>
            <Field id="inv-total-max" label={t('admin.invoices.filter.totalMax')}>
              <Input
                id="inv-total-max"
                type="number"
                inputMode="numeric"
                value={draft.total_max ?? ''}
                onChange={(e) => {
                  setDraft({ ...draft, total_max: toNullableInt(e.target.value) })
                }}
              />
            </Field>
          </div>

          <div className="audit-filters-actions">
            <label className="flex items-center gap-inline-xs text-body text-fg-muted">
              <input
                type="checkbox"
                checked={draft.overdue}
                onChange={(e) => {
                  setDraft({ ...draft, overdue: e.target.checked })
                }}
              />
              {t('admin.invoices.filter.overdue')}
            </label>
            <span className="flex-1" />
            <Button type="submit" size="sm">
              {t('admin.invoices.filter.apply')}
            </Button>
            <Button type="button" variant="ghost" size="sm" onClick={onReset}>
              {t('admin.invoices.filter.reset')}
            </Button>
          </div>
        </Stack>
      </form>

      {view.state.kind === 'loading' && <LoadingState message={t('admin.invoices.loading')} />}

      {view.state.kind === 'error' && (
        <ErrorState
          message={t('admin.invoices.error')}
          retryLabel={t('common.actions.retry')}
          onRetry={view.state.retry}
        />
      )}

      {view.state.kind === 'empty' && <EmptyState message={t('admin.invoices.empty')} />}

      {view.state.kind === 'ready' && (
        <>
          <div className="table-scroll">
            <table className="data-table">
              <thead>
                <tr>
                  {sortableTh('number', t('admin.invoices.col.number'))}
                  {sortableTh('status', t('admin.invoices.col.status'))}
                  {sortableTh('client', t('admin.invoices.col.client'))}
                  {sortableTh('due_at', t('admin.invoices.col.due'))}
                  {sortableTh('total', t('admin.invoices.col.total'), true)}
                  <th className="tr">{t('admin.invoices.col.outstanding')}</th>
                </tr>
              </thead>
              <tbody>
                {view.state.invoices.map((invoice) => (
                  <tr key={invoice.id}>
                    <td data-label={t('admin.invoices.col.number')}>
                      <Link to={`/invoices/${String(invoice.id)}`} className="num text-accent">
                        {invoice.invoice_number ?? '—'}
                      </Link>
                    </td>
                    <td data-label={t('admin.invoices.col.status')}>
                      <span className="flex items-center gap-inline-xs">
                        <Badge tone={invoiceStatusTone[invoice.status]}>
                          {t(`admin.invoices.status.${invoice.status}`)}
                        </Badge>
                        {invoice.is_overdue && (
                          <Badge tone="danger">{t('admin.invoices.status.overdue')}</Badge>
                        )}
                      </span>
                    </td>
                    <td data-label={t('admin.invoices.col.client')}>
                      {invoice.client_name ?? `#${String(invoice.client_id)}`}
                    </td>
                    <td className="num" data-label={t('admin.invoices.col.due')}>
                      {invoice.due_at ?? '—'}
                    </td>
                    <td className="tr num" data-label={t('admin.invoices.col.total')}>
                      {formatYen(invoice.total_cents)}
                    </td>
                    <td className="tr num" data-label={t('admin.invoices.col.outstanding')}>
                      {invoice.outstanding_cents === null
                        ? '—'
                        : formatYen(invoice.outstanding_cents)}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {view.pagination.totalPages > 1 && (
            <div className="flex items-center justify-between">
              <Button onClick={view.pagination.prevPage} disabled={!view.pagination.hasPrev}>
                {t('common.pagination.prev')}
              </Button>
              <Text variant="muted">
                {t('common.pagination.info', {
                  page: view.pagination.page,
                  total: view.pagination.totalPages,
                })}
              </Text>
              <Button onClick={view.pagination.nextPage} disabled={!view.pagination.hasNext}>
                {t('common.pagination.next')}
              </Button>
            </div>
          )}
        </>
      )}
    </Stack>
  )
}
