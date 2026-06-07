import { useState, type ChangeEvent } from 'react'
import { useNavigate } from 'react-router-dom'
import {
  useDownloadClientsImportTemplate,
  useImportClients,
  type ClientImportReport,
} from '@/entities/client'
import { useTranslation } from '@/shared/i18n'
import { Button, Stack, Text, useToast } from '@/shared/ui'

type Phase = 'idle' | 'previewing' | 'applying'

/** Template-only clients import: download template → upload → dry-run preview → apply. */
export function ImportClients() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { showToast } = useToast()
  const template = useDownloadClientsImportTemplate()
  const importClients = useImportClients()

  const [phase, setPhase] = useState<Phase>('idle')
  const [csv, setCsv] = useState<string | null>(null)
  const [fileName, setFileName] = useState<string | null>(null)
  const [report, setReport] = useState<ClientImportReport | null>(null)
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
        const result = await importClients(text, true)
        setReport(result)
      })
      .catch(() => {
        setError(t('admin.clients.import.uploadError'))
      })
      .finally(() => {
        setPhase('idle')
      })
  }

  const onApply = (): void => {
    if (csv === null) return
    setPhase('applying')
    setError(null)
    importClients(csv, false)
      .then((result) => {
        if (result.accepted) {
          showToast({
            tone: 'ok',
            title: t('admin.clients.import.doneTitle'),
            description: t('admin.clients.import.doneBody', {
              created: result.summary.created,
              updated: result.summary.updated,
            }),
          })
          void navigate('/clients')
          return
        }
        setReport(result)
      })
      .catch(() => {
        setError(t('admin.clients.import.uploadError'))
      })
      .finally(() => {
        setPhase('idle')
      })
  }

  const canApply = report !== null && report.accepted && report.dry_run && phase === 'idle'

  return (
    <Stack gap="md">
      <div className="flex items-center justify-between">
        <Text as="h1" variant="heading-md">
          {t('admin.clients.import.title')}
        </Text>
        <Button variant="ghost" size="sm" onClick={() => void navigate('/clients')}>
          {t('admin.clients.import.back')}
        </Button>
      </div>

      <Text variant="muted">{t('admin.clients.import.lede')}</Text>

      <Stack direction="row" gap="sm">
        <Button
          variant="ghost"
          size="sm"
          onClick={template.download}
          disabled={template.isDownloading}
        >
          {template.isDownloading
            ? t('admin.clients.export.downloading')
            : t('admin.clients.import.downloadTemplate')}
        </Button>
        <input
          type="file"
          accept=".csv,text/csv"
          aria-label={t('admin.clients.import.fileLabel')}
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
      {phase === 'previewing' && <Text variant="muted">{t('admin.clients.import.checking')}</Text>}

      {report !== null && <ImportReport report={report} fileName={fileName} />}

      {canApply && (
        <div>
          <Button onClick={onApply}>{t('admin.clients.import.apply')}</Button>
        </div>
      )}
      {phase === 'applying' && <Text variant="muted">{t('admin.clients.import.applying')}</Text>}
    </Stack>
  )
}

function ImportReport({
  report,
  fileName,
}: {
  report: ClientImportReport
  fileName: string | null
}) {
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
        {t('admin.clients.import.summary', {
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
                <th>{t('admin.clients.import.col.row')}</th>
                <th>{t('admin.clients.import.col.column')}</th>
                <th>{t('admin.clients.import.col.message')}</th>
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
