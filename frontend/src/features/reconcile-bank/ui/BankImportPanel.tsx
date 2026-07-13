import { useRef, useState, type ChangeEvent, type DragEvent } from 'react'
import {
  BANK_IMPORT_PRESETS,
  useImportBankCsv,
  type BankImportPreset,
  type BankImportResult,
} from '@/entities/bank-transaction'
import { useTranslation } from '@/shared/i18n'
import { cn } from '@/shared/lib/cn'
import { Badge, Button, Field, Select, Stack, Text, useToast } from '@/shared/ui'

function formatSize(bytes: number): string {
  if (bytes < 1024) return `${String(bytes)} B`
  const kb = bytes / 1024
  if (kb < 1024) return `${kb.toFixed(1)} KB`
  return `${(kb / 1024).toFixed(1)} MB`
}

/**
 * Bank CSV import (自動消込 ⑧). Unlike the template-CSV panel this sends the RAW
 * file bytes with `Content-Type: text/csv` and never calls `File.text()`, so
 * Shift_JIS statements (common in Japanese banking) are not corrupted by a UTF-8
 * decode. The preset selects the column layout; the result summary reports staged
 * / duplicate / error counts.
 */
export function BankImportPanel() {
  const { t } = useTranslation()
  const { showToast } = useToast()
  const importCsv = useImportBankCsv()
  const inputRef = useRef<HTMLInputElement>(null)

  const [preset, setPreset] = useState<BankImportPreset>('net_bank_credit_debit')
  const [file, setFile] = useState<File | null>(null)
  const [result, setResult] = useState<BankImportResult | null>(null)
  const [drag, setDrag] = useState(false)

  const pick = (picked: File): void => {
    setResult(null)
    setFile(picked)
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
    setFile(null)
    setResult(null)
    if (inputRef.current !== null) inputRef.current.value = ''
  }

  const runImport = (): void => {
    if (file === null) return
    importCsv.mutate(
      { file, preset },
      {
        onSuccess: (report) => {
          setResult(report)
          if (report.format_error === null) {
            showToast({
              tone: 'ok',
              title: t('admin.bankReconciliation.import.doneTitle'),
              description: t('admin.bankReconciliation.import.doneBody', {
                imported: report.imported_count,
                skipped: report.skipped_duplicate_count,
              }),
            })
            reset()
          }
        },
        onError: () => {
          showToast({
            tone: 'err',
            title: t('admin.bankReconciliation.import.errorTitle'),
            description: t('admin.bankReconciliation.import.errorBody'),
          })
        },
      },
    )
  }

  return (
    <Stack gap="md" className="card">
      <div>
        <Text as="h2" variant="heading-sm">
          {t('admin.bankReconciliation.import.title')}
        </Text>
        <Text variant="muted">{t('admin.bankReconciliation.import.lede')}</Text>
      </div>

      <Field id="bank-preset" label={t('admin.bankReconciliation.import.preset')}>
        <Select
          id="bank-preset"
          value={preset}
          onChange={(e) => {
            setPreset(e.target.value as BankImportPreset)
          }}
        >
          {BANK_IMPORT_PRESETS.map((p) => (
            <option key={p} value={p}>
              {t(`admin.bankReconciliation.import.presetOption.${p}`)}
            </option>
          ))}
        </Select>
      </Field>

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
          ref={inputRef}
          type="file"
          accept=".csv,text/csv"
          aria-label={t('admin.bankReconciliation.import.fileLabel')}
          onChange={onInput}
          disabled={importCsv.isPending}
        />
        <div className="dz-main">
          <b>{t('admin.bankReconciliation.import.dropMainAction')}</b>
          {t('admin.bankReconciliation.import.dropMainRest')}
        </div>
        <div className="dz-sub">{t('admin.bankReconciliation.import.dropSub')}</div>
      </label>

      {file !== null && (
        <Stack direction="row" gap="sm" className="justify-between">
          <Text variant="muted">
            {file.name} · {formatSize(file.size)}
          </Text>
          <Stack direction="row" gap="sm">
            <Button variant="ghost" size="sm" onClick={reset} disabled={importCsv.isPending}>
              {t('admin.bankReconciliation.import.clear')}
            </Button>
            <Button size="sm" onClick={runImport} disabled={importCsv.isPending}>
              {importCsv.isPending
                ? t('admin.bankReconciliation.import.importing')
                : t('admin.bankReconciliation.import.import')}
            </Button>
          </Stack>
        </Stack>
      )}

      {result !== null && <ImportResult result={result} />}
    </Stack>
  )
}

function ImportResult({ result }: { result: BankImportResult }) {
  const { t } = useTranslation()

  if (result.format_error !== null) {
    return (
      <Text variant="muted" role="alert">
        {t('admin.bankReconciliation.import.formatError', { reason: result.format_error })}
      </Text>
    )
  }

  return (
    <Stack gap="sm">
      <Stack direction="row" gap="sm">
        <Badge tone="ok">
          {t('admin.bankReconciliation.import.staged', { count: result.imported_count })}
        </Badge>
        <Badge tone="neutral">
          {t('admin.bankReconciliation.import.skipped', {
            count: result.skipped_duplicate_count,
          })}
        </Badge>
        <Badge tone={result.row_errors.length > 0 ? 'danger' : 'neutral'}>
          {t('admin.bankReconciliation.import.rowErrors', { count: result.row_errors.length })}
        </Badge>
      </Stack>

      {result.row_errors.length > 0 && (
        <div className="table-scroll">
          <table className="data-table">
            <thead>
              <tr>
                <th className="num">{t('admin.bankReconciliation.import.col.line')}</th>
                <th>{t('admin.bankReconciliation.import.col.reason')}</th>
              </tr>
            </thead>
            <tbody>
              {result.row_errors.map((e, i) => (
                <tr key={`${String(e.line)}-${String(i)}`}>
                  <td className="num">{e.line}</td>
                  <td>{e.reason}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </Stack>
  )
}
