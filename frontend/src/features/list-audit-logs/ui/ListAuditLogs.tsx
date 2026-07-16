import { useState, type SyntheticEvent } from 'react'
import {
  AUDIT_ACTIONS,
  AUDIT_ENTITY_TYPES,
  auditActionLabelKey,
  auditEntityLabelKey,
  EMPTY_AUDIT_LOG_FILTERS,
  useExportAuditLogsCsv,
  type AuditLog,
  type AuditLogFilters,
} from '@/entities/audit'
import { useTranslation } from '@/shared/i18n'
import { formatJstDateTime } from '@/shared/lib/format-date'
import {
  Button,
  DatePicker,
  EmptyState,
  ErrorState,
  Field,
  FilterBar,
  Input,
  LoadingState,
  Select,
  Stack,
  Text,
} from '@/shared/ui'
import { useListAuditLogs } from '../model/use-list-audit-logs'

function trimmedOrNull(value: string): string | null {
  const trimmed = value.trim()
  return trimmed === '' ? null : trimmed
}

/** Audit-trail screen: filter bar, table, and per-row before/after diff. */
export function ListAuditLogs() {
  const { t } = useTranslation()
  const view = useListAuditLogs()
  const exportCsv = useExportAuditLogsCsv(view.filters)
  const [expanded, setExpanded] = useState<number | null>(null)

  // Local draft of the filter form; committed to the query on submit.
  const [draft, setDraft] = useState<AuditLogFilters>(EMPTY_AUDIT_LOG_FILTERS)

  const onSubmit = (event: SyntheticEvent): void => {
    event.preventDefault()
    view.applyFilters(draft)
  }

  const onReset = (): void => {
    setDraft(EMPTY_AUDIT_LOG_FILTERS)
    view.resetFilters()
  }

  return (
    <Stack gap="md">
      <div className="flex items-center justify-between">
        <Text as="h1" variant="heading-md">
          {t('admin.audit.title')}
        </Text>
        <Button
          variant="ghost"
          size="sm"
          onClick={exportCsv.download}
          disabled={exportCsv.isDownloading}
        >
          {exportCsv.isDownloading ? t('admin.audit.exporting') : t('admin.audit.export')}
        </Button>
      </div>

      {exportCsv.errorMessage !== null && (
        <Text variant="muted" role="alert">
          {exportCsv.errorMessage}
        </Text>
      )}

      <FilterBar count={view.total} onSubmit={onSubmit} onReset={onReset}>
        <Field id="audit-entity-type" label={t('admin.audit.filter.entityType')}>
          <Select
            id="audit-entity-type"
            value={draft.entity_type ?? ''}
            onChange={(e) => {
              setDraft({ ...draft, entity_type: trimmedOrNull(e.target.value) })
            }}
          >
            <option value="">{t('admin.audit.filter.any')}</option>
            {AUDIT_ENTITY_TYPES.map((type) => {
              const key = auditEntityLabelKey(type)
              return (
                <option key={type} value={type}>
                  {key ? t(key) : type}
                </option>
              )
            })}
          </Select>
        </Field>

        <Field id="audit-action" label={t('admin.audit.filter.action')}>
          <Select
            id="audit-action"
            value={draft.action ?? ''}
            onChange={(e) => {
              setDraft({ ...draft, action: trimmedOrNull(e.target.value) })
            }}
          >
            <option value="">{t('admin.audit.filter.any')}</option>
            {AUDIT_ACTIONS.map((action) => {
              const key = auditActionLabelKey(action)
              return (
                <option key={action} value={action}>
                  {key ? t(key) : action}
                </option>
              )
            })}
          </Select>
        </Field>

        <Field id="audit-actor" label={t('admin.audit.filter.actor')}>
          <Input
            id="audit-actor"
            type="number"
            inputMode="numeric"
            min={1}
            value={draft.actor_user_id ?? ''}
            onChange={(e) => {
              const n = Number.parseInt(e.target.value, 10)
              setDraft({ ...draft, actor_user_id: Number.isNaN(n) ? null : n })
            }}
          />
        </Field>

        <Field id="audit-from" label={t('admin.audit.filter.from')}>
          <DatePicker
            id="audit-from"
            value={draft.created_from ?? ''}
            onChange={(v) => {
              setDraft({ ...draft, created_from: v === '' ? null : v })
            }}
          />
        </Field>

        <Field id="audit-to" label={t('admin.audit.filter.to')}>
          <DatePicker
            id="audit-to"
            value={draft.created_to ?? ''}
            onChange={(v) => {
              setDraft({ ...draft, created_to: v === '' ? null : v })
            }}
          />
        </Field>
      </FilterBar>

      {view.state.kind === 'loading' && <LoadingState message={t('admin.audit.loading')} />}

      {view.state.kind === 'error' && (
        <ErrorState
          message={t('admin.audit.error')}
          retryLabel={t('common.actions.retry')}
          onRetry={view.state.retry}
        />
      )}

      {view.state.kind === 'empty' && <EmptyState message={t('admin.audit.empty')} />}

      {view.state.kind === 'ready' && (
        <Stack gap="sm">
          <div className="table-scroll">
            <table className="data-table">
              <thead>
                <tr>
                  <th>{t('admin.audit.col.createdAt')}</th>
                  <th>{t('admin.audit.col.action')}</th>
                  <th>{t('admin.audit.col.entity')}</th>
                  <th>{t('admin.audit.col.actor')}</th>
                  <th className="tr">{t('admin.audit.col.detail')}</th>
                </tr>
              </thead>
              <tbody>
                {view.state.logs.map((log) => (
                  <AuditRow
                    key={log.id}
                    log={log}
                    expanded={expanded === log.id}
                    onToggle={() => {
                      setExpanded((current) => (current === log.id ? null : log.id))
                    }}
                  />
                ))}
              </tbody>
            </table>
          </div>

          <div className="flex items-center justify-between">
            <Text variant="muted">
              {t('common.pagination.info', { page: view.page, total: view.pageCount })}
            </Text>
            <Stack direction="row" gap="sm">
              <Button variant="ghost" size="sm" disabled={!view.canPrev} onClick={view.goPrev}>
                {t('common.pagination.prev')}
              </Button>
              <Button variant="ghost" size="sm" disabled={!view.canNext} onClick={view.goNext}>
                {t('common.pagination.next')}
              </Button>
            </Stack>
          </div>
        </Stack>
      )}
    </Stack>
  )
}

interface AuditRowProps {
  log: AuditLog
  expanded: boolean
  onToggle: () => void
}

function AuditRow({ log, expanded, onToggle }: AuditRowProps) {
  const { t } = useTranslation()

  const actionKey = auditActionLabelKey(log.action)
  const actionLabel = actionKey ? t(actionKey) : log.action
  const entityKey = auditEntityLabelKey(log.entity_type)
  const entityLabel = entityKey ? t(entityKey) : log.entity_type

  return (
    <>
      <tr>
        <td data-label={t('admin.audit.col.createdAt')}>
          {log.created_at !== null ? formatJstDateTime(log.created_at) : '—'}
        </td>
        <td data-label={t('admin.audit.col.action')}>
          {/* Accounting-term label; the raw code stays available on hover. */}
          <span title={log.action}>{actionLabel}</span>
        </td>
        <td data-label={t('admin.audit.col.entity')}>
          {entityLabel}
          {log.entity_id !== null ? ` #${String(log.entity_id)}` : ''}
        </td>
        <td data-label={t('admin.audit.col.actor')}>
          {log.actor_email ??
            (log.actor_user_id !== null
              ? `#${String(log.actor_user_id)}`
              : t('admin.audit.system'))}
        </td>
        <td className="tr" data-label={t('admin.audit.col.detail')}>
          <Button variant="ghost" size="sm" onClick={onToggle} aria-expanded={expanded}>
            {expanded ? t('admin.audit.detail.hide') : t('admin.audit.detail.show')}
          </Button>
        </td>
      </tr>
      {expanded && (
        <tr>
          <td colSpan={5}>
            <AuditDiff before={log.before} after={log.after} />
          </td>
        </tr>
      )}
    </>
  )
}

interface AuditDiffProps {
  before: Record<string, unknown> | null
  after: Record<string, unknown> | null
}

function formatValue(value: unknown): string {
  if (value === undefined) return '—'
  if (value === null) return 'null'
  if (typeof value === 'string') return value
  if (typeof value === 'number' || typeof value === 'boolean' || typeof value === 'bigint') {
    return String(value)
  }
  return JSON.stringify(value)
}

/** Side-by-side before/after with changed keys highlighted. */
function AuditDiff({ before, after }: AuditDiffProps) {
  const { t } = useTranslation()
  const keys = Array.from(
    new Set([...Object.keys(before ?? {}), ...Object.keys(after ?? {})]),
  ).sort()

  if (keys.length === 0) {
    return <Text variant="muted">{t('admin.audit.detail.noPayload')}</Text>
  }

  return (
    <div className="audit-diff">
      <div>
        <Text variant="muted">{t('admin.audit.detail.before')}</Text>
        <dl className="audit-kv">
          {keys.map((key) => {
            const changed = formatValue(before?.[key]) !== formatValue(after?.[key])
            return (
              <div key={key} className={changed ? 'audit-kv-row changed' : 'audit-kv-row'}>
                <strong>{key}:</strong> {before === null ? '—' : formatValue(before[key])}
              </div>
            )
          })}
        </dl>
      </div>
      <div>
        <Text variant="muted">{t('admin.audit.detail.after')}</Text>
        <dl className="audit-kv">
          {keys.map((key) => {
            const changed = formatValue(before?.[key]) !== formatValue(after?.[key])
            return (
              <div key={key} className={changed ? 'audit-kv-row changed' : 'audit-kv-row'}>
                <strong>{key}:</strong> {after === null ? '—' : formatValue(after[key])}
              </div>
            )
          })}
        </dl>
      </div>
    </div>
  )
}
