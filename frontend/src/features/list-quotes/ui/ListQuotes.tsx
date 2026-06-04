import { useState, type ReactNode, type SyntheticEvent } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import {
  EMPTY_QUOTE_FILTERS,
  QUOTE_STATUSES,
  quoteStatusTone,
  type QuoteListFilters,
  type QuoteSortField,
  type QuoteStatus,
} from '@/entities/quote'
import { useTranslation } from '@/shared/i18n'
import { KbdHint, useRowCursor } from '@/shared/keyboard'
import { formatYen } from '@/shared/lib/format-money'
import {
  Badge,
  Button,
  DatePicker,
  EmptyState,
  ErrorState,
  Field,
  Input,
  LinkButton,
  LoadingState,
  Select,
  SortableTh,
  Stack,
  Text,
} from '@/shared/ui'
import { useListQuotes } from '../hooks/use-list-quotes'

const toNullableInt = (value: string): number | null => {
  const n = Number.parseInt(value, 10)
  return Number.isNaN(n) ? null : n
}
const trimmedOrNull = (value: string): string | null => (value.trim() === '' ? null : value.trim())

export function ListQuotes() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const view = useListQuotes()
  const [draft, setDraft] = useState<QuoteListFilters>(EMPTY_QUOTE_FILTERS)

  const rows = view.state.kind === 'ready' ? view.state.quotes : []
  const cursor = useRowCursor(rows.length, (index) => {
    const row = rows[index]
    if (row !== undefined) void navigate(`/quotes/${String(row.id)}`)
  })

  const onSubmit = (event: SyntheticEvent): void => {
    event.preventDefault()
    view.applyFilters(draft)
  }
  const onReset = (): void => {
    setDraft(EMPTY_QUOTE_FILTERS)
    view.resetFilters()
  }

  const sortableTh = (field: QuoteSortField, label: string, right = false): ReactNode => (
    <SortableTh
      label={label}
      active={view.sort.field === field}
      order={view.sort.order}
      right={right}
      onToggle={() => {
        view.toggleSort(field)
      }}
    />
  )

  return (
    <Stack gap="md">
      <div className="flex items-center justify-between">
        <Text as="h1" variant="heading-md">
          {t('admin.quotes.title')}
        </Text>
        <LinkButton to="/quotes/new" size="sm" aria-keyshortcuts="n">
          {t('admin.quotes.newButton')}
        </LinkButton>
      </div>

      <form onSubmit={onSubmit}>
        <Stack gap="sm">
          <div className="audit-filters">
            <Field id="q-q" label={t('admin.quotes.filter.search')}>
              <div className="field-kbd">
                <Input
                  id="q-q"
                  data-kbd="search"
                  aria-keyshortcuts="/"
                  className="pr-9"
                  value={draft.q ?? ''}
                  placeholder={t('admin.quotes.filter.searchPlaceholder')}
                  onChange={(e) => {
                    setDraft({ ...draft, q: trimmedOrNull(e.target.value) })
                  }}
                />
                <KbdHint>/</KbdHint>
              </div>
            </Field>
            <Field id="q-status" label={t('admin.quotes.filter.status')}>
              <Select
                id="q-status"
                value={draft.statuses[0] ?? ''}
                onChange={(e) => {
                  const v = e.target.value
                  setDraft({ ...draft, statuses: v === '' ? [] : [v as QuoteStatus] })
                }}
              >
                <option value="">{t('admin.quotes.filter.statusAny')}</option>
                {QUOTE_STATUSES.map((s) => (
                  <option key={s} value={s}>
                    {t(`admin.quotes.status.${s}`)}
                  </option>
                ))}
              </Select>
            </Field>
            <Field id="q-valid-from" label={t('admin.quotes.filter.validFrom')}>
              <DatePicker
                id="q-valid-from"
                value={draft.valid_from ?? ''}
                onChange={(v) => {
                  setDraft({ ...draft, valid_from: v === '' ? null : v })
                }}
              />
            </Field>
            <Field id="q-valid-to" label={t('admin.quotes.filter.validTo')}>
              <DatePicker
                id="q-valid-to"
                value={draft.valid_to ?? ''}
                onChange={(v) => {
                  setDraft({ ...draft, valid_to: v === '' ? null : v })
                }}
              />
            </Field>
            <Field id="q-total-min" label={t('admin.quotes.filter.totalMin')}>
              <Input
                id="q-total-min"
                type="number"
                inputMode="numeric"
                value={draft.total_min ?? ''}
                onChange={(e) => {
                  setDraft({ ...draft, total_min: toNullableInt(e.target.value) })
                }}
              />
            </Field>
            <Field id="q-total-max" label={t('admin.quotes.filter.totalMax')}>
              <Input
                id="q-total-max"
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
            <span className="flex-1" />
            <Button type="submit" size="sm">
              {t('admin.quotes.filter.apply')}
            </Button>
            <Button type="button" variant="ghost" size="sm" onClick={onReset}>
              {t('admin.quotes.filter.reset')}
            </Button>
          </div>
        </Stack>
      </form>

      {view.state.kind === 'loading' && <LoadingState message={t('admin.quotes.loading')} />}

      {view.state.kind === 'error' && (
        <ErrorState
          message={t('admin.quotes.error')}
          retryLabel={t('common.actions.retry')}
          onRetry={view.state.retry}
        />
      )}

      {view.state.kind === 'empty' && <EmptyState message={t('admin.quotes.empty')} />}

      {view.state.kind === 'ready' && (
        <>
          <div className="table-scroll">
            <table className="data-table">
              <thead>
                <tr>
                  {sortableTh('number', t('admin.quotes.col.number'))}
                  {sortableTh('status', t('admin.quotes.col.status'))}
                  {sortableTh('client', t('admin.quotes.col.client'))}
                  {sortableTh('valid_until', t('admin.quotes.col.validUntil'))}
                  {sortableTh('total', t('admin.quotes.col.total'), true)}
                </tr>
              </thead>
              <tbody>
                {view.state.quotes.map((quote, index) => (
                  <tr
                    key={quote.id}
                    data-kbd-row={index}
                    className={cursor === index ? 'is-cursor' : undefined}
                  >
                    <td data-label={t('admin.quotes.col.number')}>
                      <Link to={`/quotes/${String(quote.id)}`} className="num text-accent">
                        {quote.quote_number}
                      </Link>
                    </td>
                    <td data-label={t('admin.quotes.col.status')}>
                      <Badge tone={quoteStatusTone[quote.status]} className="badge-status">
                        {t(`admin.quotes.status.${quote.status}`)}
                      </Badge>
                    </td>
                    <td data-label={t('admin.quotes.col.client')}>
                      {quote.client_name ?? `#${String(quote.client_id)}`}
                    </td>
                    <td className="num" data-label={t('admin.quotes.col.validUntil')}>
                      {quote.valid_until ?? '—'}
                    </td>
                    <td className="tr num" data-label={t('admin.quotes.col.total')}>
                      {formatYen(quote.total_cents)}
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
