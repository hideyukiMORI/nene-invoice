import { useState, type ChangeEvent } from 'react'
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

/**
 * Generic template-only CSV import widget (ADR 0011): download template → choose
 * file → auto dry-run preview (summary + per-row errors) → apply. Shared by the
 * clients and items import pages; entity-specific chrome (title, lede) lives in
 * the page, the mechanics + report rendering live here.
 */
export function CsvImportPanel({ template, runImport, onDone }: CsvImportPanelProps) {
  const { t } = useTranslation()
  const [phase, setPhase] = useState<Phase>('idle')
  const [csv, setCsv] = useState<string | null>(null)
  const [fileName, setFileName] = useState<string | null>(null)
  const [report, setReport] = useState<CsvImportReport | null>(null)
  const [error, setError] = useState<string | null>(null)

  const onFile = (event: ChangeEvent<HTMLInputElement>): void => {
    const file = event.target.files?.[0]
    if (file === undefined) return
    setError(null)
    setReport(null)
    setFileName(file.name)
    setPhase('previewing')
    file
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

  const onApply = (): void => {
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

  const canApply = report !== null && report.accepted && report.dry_run && phase === 'idle'

  return (
    <Stack gap="md">
      <Stack direction="row" gap="sm">
        <Button
          variant="ghost"
          size="sm"
          onClick={template.download}
          disabled={template.isDownloading}
        >
          {template.isDownloading
            ? t('common.csvImport.downloading')
            : t('common.csvImport.downloadTemplate')}
        </Button>
        <input
          type="file"
          accept=".csv,text/csv"
          aria-label={t('common.csvImport.fileLabel')}
          className="block text-body"
          onChange={onFile}
          disabled={phase !== 'idle'}
        />
      </Stack>

      {template.errorMessage !== null && (
        <Text variant="muted" role="alert">
          {template.errorMessage}
        </Text>
      )}
      {error !== null && (
        <Text variant="muted" role="alert">
          {error}
        </Text>
      )}
      {phase === 'previewing' && <Text variant="muted">{t('common.csvImport.checking')}</Text>}

      {report !== null && <ImportReport report={report} fileName={fileName} />}

      {canApply && (
        <div>
          <Button onClick={onApply}>{t('common.csvImport.apply')}</Button>
        </div>
      )}
      {phase === 'applying' && <Text variant="muted">{t('common.csvImport.applying')}</Text>}
    </Stack>
  )
}

function ImportReport({ report, fileName }: { report: CsvImportReport; fileName: string | null }) {
  const { t } = useTranslation()

  if (report.format_error !== null) {
    return (
      <Text role="alert">
        {fileName !== null ? `${fileName}: ` : ''}
        {report.format_error}
      </Text>
    )
  }

  return (
    <Stack gap="sm">
      <Text>
        {t('common.csvImport.summary', {
          rows: report.summary.rows,
          created: report.summary.created,
          updated: report.summary.updated,
          errors: report.summary.errors,
        })}
      </Text>
      {report.errors.length > 0 && (
        <div className="table-scroll">
          <table className="data-table">
            <thead>
              <tr>
                <th>{t('common.csvImport.col.row')}</th>
                <th>{t('common.csvImport.col.column')}</th>
                <th>{t('common.csvImport.col.message')}</th>
              </tr>
            </thead>
            <tbody>
              {report.errors.map((e, i) => (
                <tr key={`${String(e.row)}-${String(i)}`}>
                  <td className="num">{e.row}</td>
                  <td>{e.column ?? '—'}</td>
                  <td>{e.message}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </Stack>
  )
}
