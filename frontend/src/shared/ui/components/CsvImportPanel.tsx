import { useState, type ChangeEvent, type DragEvent } from 'react'
import { cn } from '@/shared/lib/cn'
import type { CsvImportReport } from '@/shared/lib/csv-import'
import { useTranslation } from '@/shared/i18n'
import { Button } from '../primitives/Button'
import { Stack } from '../primitives/Stack'
import { Text } from '../primitives/Text'

type Phase = 'idle' | 'previewing' | 'applying'

/** Structural shape of a template-download hook (matches `UseExportCsv`). */
interface TemplateDownload {
  download: () => void
  isDownloading: boolean
  errorMessage: string | null
}

export interface CsvImportPanelProps {
  /** Template-download hook (GET the header-only CSV). */
  template: TemplateDownload
  /** Posts the CSV; `dryRun` validates without writing. Resolves the report (incl. 422). */
  runImport: (csv: string, dryRun: boolean) => Promise<CsvImportReport>
  /** Called after a real (non-dry-run) accepted import, with the applied counts. */
  onDone: (summary: { created: number; updated: number }) => void
}

interface PickedFile {
  name: string
  size: string
}

function formatSize(bytes: number): string {
  if (bytes < 1024) return `${String(bytes)} B`
  const kb = bytes / 1024
  if (kb < 1024) return `${kb.toFixed(1)} KB`
  return `${(kb / 1024).toFixed(1)} MB`
}

/**
 * Template-only CSV import widget (ADR 0011), design #390: stepped panel
 * (download template → drop/choose file) → auto dry-run preview (summary stats +
 * per-row errors) → apply. Shared by the clients and items import pages; the page
 * supplies the entity-specific chrome (crumb, title, lede).
 */
export function CsvImportPanel({ template, runImport, onDone }: CsvImportPanelProps) {
  const { t } = useTranslation()
  const [phase, setPhase] = useState<Phase>('idle')
  const [file, setFile] = useState<PickedFile | null>(null)
  const [csv, setCsv] = useState<string | null>(null)
  const [report, setReport] = useState<CsvImportReport | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [drag, setDrag] = useState(false)

  const pick = (picked: File): void => {
    setError(null)
    setReport(null)
    setFile({ name: picked.name, size: formatSize(picked.size) })
    setPhase('previewing')
    picked
      .text()
      .then(async (text) => {
        setCsv(text)
        setReport(await runImport(text, true))
      })
      .catch(() => {
        setError(t('common.csvImport.uploadError'))
      })
      .finally(() => {
        setPhase('idle')
      })
  }

  const onInput = (event: ChangeEvent<HTMLInputElement>): void => {
    const picked = event.target.files?.[0]
    if (picked !== undefined) pick(picked)
  }

  const onDrop = (event: DragEvent<HTMLLabelElement>): void => {
    event.preventDefault()
    setDrag(false)
    const picked = event.dataTransfer.files[0]
    if (picked !== undefined) pick(picked)
  }

  const reset = (): void => {
    setPhase('idle')
    setFile(null)
    setCsv(null)
    setReport(null)
    setError(null)
  }

  const apply = (): void => {
    if (csv === null) return
    setPhase('applying')
    setError(null)
    runImport(csv, false)
      .then((result) => {
        if (result.accepted) {
          onDone({ created: result.summary.created, updated: result.summary.updated })
          return
        }
        setReport(result)
      })
      .catch(() => {
        setError(t('common.csvImport.uploadError'))
      })
      .finally(() => {
        setPhase('idle')
      })
  }

  return (
    <Stack gap="md">
      {/* ① download template  ② choose / drop file (always visible) */}
      <div className="panel">
        <div className="panel-step">
          <span className="step-no">1</span>
          <div className="step-body">
            <div className="st-t">{t('common.csvImport.step1Title')}</div>
            <div className="st-d">{t('common.csvImport.step1Desc')}</div>
          </div>
          <div className="panel-actions">
            <Button
              variant="ghost"
              size="sm"
              className="gap-inline-xs"
              onClick={template.download}
              disabled={template.isDownloading}
            >
              <svg
                width={15}
                height={15}
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth={2}
                aria-hidden="true"
              >
                <path d="M12 3v12m0 0l-4-4m4 4l4-4M5 21h14" />
              </svg>
              {template.isDownloading
                ? t('common.csvImport.downloading')
                : t('common.csvImport.downloadTemplate')}
            </Button>
          </div>
        </div>

        <div className="panel-step is-upload">
          <div className="step-head">
            <span className="step-no">2</span>
            <div className="step-body">
              <div className="st-t">{t('common.csvImport.step2Title')}</div>
              <div className="st-d">{t('common.csvImport.step2Desc')}</div>
            </div>
          </div>
          <label
            className={cn('dropzone', drag && 'is-drag')}
            onDragEnter={(e) => {
              e.preventDefault()
              setDrag(true)
            }}
            onDragOver={(e) => {
              e.preventDefault()
            }}
            onDragLeave={() => {
              setDrag(false)
            }}
            onDrop={onDrop}
          >
            <input
              type="file"
              accept=".csv,text/csv"
              aria-label={t('common.csvImport.fileLabel')}
              onChange={onInput}
              disabled={phase !== 'idle'}
            />
            <svg
              className="dz-ico"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              strokeWidth={1.7}
              aria-hidden="true"
            >
              <path d="M12 16V4m0 0L7 9m5-5l5 5" />
              <path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2" />
            </svg>
            <div className="dz-main">
              <b>{t('common.csvImport.dropMainAction')}</b>
              {t('common.csvImport.dropMainRest')}
            </div>
            <div className="dz-sub">{t('common.csvImport.dropSub')}</div>
          </label>
        </div>
      </div>

      {/* slot: selected file + state */}
      {file !== null && (
        <Stack gap="sm">
          <FileChip
            name={file.name}
            size={file.size}
            onClear={reset}
            removeLabel={t('common.csvImport.removeFile')}
          />

          {error !== null && (
            <Text variant="muted" role="alert">
              {error}
            </Text>
          )}

          {phase === 'previewing' && (
            <div className="status-line" role="status">
              <span className="spinner" />
              {t('common.csvImport.checking')}
            </div>
          )}
          {phase === 'applying' && (
            <div className="status-line" role="status">
              <span className="spinner" />
              {t('common.csvImport.applying')}
            </div>
          )}

          {phase === 'idle' && report !== null && report.format_error !== null && (
            <>
              <p className="alert format-error" role="alert">
                <svg
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  strokeWidth={2}
                  aria-hidden="true"
                >
                  <circle cx="12" cy="12" r="9" />
                  <path d="M12 8v5m0 3h.01" />
                </svg>
                <span>
                  <span className="fe-file">{file.name}</span>
                  {`: ${report.format_error}`}
                </span>
              </p>
              <div className="apply-bar">
                <span className="spacer" />
                <Button variant="ghost" size="sm" onClick={reset}>
                  {t('common.csvImport.reselect')}
                </Button>
              </div>
            </>
          )}

          {phase === 'idle' && report !== null && report.format_error === null && (
            <>
              <ReportView report={report} />
              <ApplyBar report={report} onReselect={reset} onApply={apply} />
            </>
          )}
        </Stack>
      )}

      {template.errorMessage !== null && (
        <Text variant="muted" role="alert">
          {template.errorMessage}
        </Text>
      )}
    </Stack>
  )
}

function FileChip({
  name,
  size,
  onClear,
  removeLabel,
}: {
  name: string
  size: string
  onClear: () => void
  removeLabel: string
}) {
  return (
    <div className="file-chip">
      <svg
        className="fc-ico"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        strokeWidth={1.8}
        aria-hidden="true"
      >
        <path d="M14 3v5h5" />
        <path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
      </svg>
      <span className="fc-name">{name}</span>
      <span className="fc-size">{size}</span>
      <button
        type="button"
        className="fc-x"
        title={removeLabel}
        aria-label={removeLabel}
        onClick={onClear}
      >
        <svg
          width={13}
          height={13}
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          strokeWidth={2}
          aria-hidden="true"
        >
          <path d="M6 6l12 12M18 6L6 18" />
        </svg>
      </button>
    </div>
  )
}

function ReportView({ report }: { report: CsvImportReport }) {
  const { t } = useTranslation()
  const { rows, created, updated, errors } = report.summary
  const hasErrors = errors > 0

  return (
    <div className="report">
      <div className="report-summary">
        <div className="rs-total">
          <span className="rt-n">{rows}</span>
          <span className="rt-l">{t('common.csvImport.rowsUnit')}</span>
        </div>
        <div className="rs-stats">
          <Stat
            n={created}
            label={t('common.csvImport.statNew')}
            unit={t('common.csvImport.unit')}
            kind="new"
          />
          <Stat
            n={updated}
            label={t('common.csvImport.statUpd')}
            unit={t('common.csvImport.unit')}
            kind="upd"
          />
          <Stat
            n={errors}
            label={t('common.csvImport.statErr')}
            unit={t('common.csvImport.unit')}
            kind={hasErrors ? 'err' : 'err zero'}
          />
        </div>
      </div>

      {hasErrors && (
        <>
          <div className="report-tablehd">
            <svg
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              strokeWidth={2}
              aria-hidden="true"
            >
              <path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z" />
              <path d="M12 9v4m0 4h.01" />
            </svg>
            {t('common.csvImport.errorBand', { errors })}
          </div>
          <div className="table-scroll">
            <table className="data-table">
              <thead>
                <tr>
                  <th className="num">{t('common.csvImport.col.row')}</th>
                  <th>{t('common.csvImport.col.column')}</th>
                  <th>{t('common.csvImport.col.message')}</th>
                </tr>
              </thead>
              <tbody>
                {report.errors.map((e, i) => (
                  <tr key={`${String(e.row)}-${String(i)}`}>
                    <td className="num">{e.row}</td>
                    <td className="col-name">{e.column ?? '—'}</td>
                    <td className="row-ref">{e.message}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </>
      )}
    </div>
  )
}

function Stat({ n, label, unit, kind }: { n: number; label: string; unit: string; kind: string }) {
  return (
    <div className={cn('stat', kind)}>
      <span className="s-n">
        {n}
        <span className="u">{unit}</span>
      </span>
      <span className="s-l">
        <span className="pip" />
        {label}
      </span>
    </div>
  )
}

function ApplyBar({
  report,
  onReselect,
  onApply,
}: {
  report: CsvImportReport
  onReselect: () => void
  onApply: () => void
}) {
  const { t } = useTranslation()
  const blocked = report.summary.errors > 0

  return (
    <div className="apply-bar">
      <span className={cn('ab-note', blocked && 'block')}>
        {blocked
          ? t('common.csvImport.applyNoteBlocked')
          : t('common.csvImport.applyNoteClean', {
              created: report.summary.created,
              updated: report.summary.updated,
            })}
      </span>
      <span className="spacer" />
      <Button variant="ghost" size="sm" onClick={onReselect}>
        {t('common.csvImport.reselect')}
      </Button>
      <Button onClick={onApply} disabled={blocked}>
        {t('common.csvImport.apply')}
      </Button>
    </div>
  )
}
