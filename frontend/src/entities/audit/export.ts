import { useState } from 'react'
import { apiClient } from '@/shared/api/client'
import { useTranslation } from '@/shared/i18n'
import type { AuditLogFilters } from './model'

export interface UseExportAuditLogsCsv {
  download: () => void
  isDownloading: boolean
  errorMessage: string | null
}

/** Downloads the audit trail as CSV, reflecting the applied filters. */
export function useExportAuditLogsCsv(filters: AuditLogFilters): UseExportAuditLogsCsv {
  const { t } = useTranslation()
  const [isDownloading, setIsDownloading] = useState(false)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)

  const download = (): void => {
    if (isDownloading) return
    setIsDownloading(true)
    setErrorMessage(null)

    const search = new URLSearchParams()
    if (filters.entity_type !== null) search.set('entity_type', filters.entity_type)
    if (filters.action !== null) search.set('action', filters.action)
    if (filters.actor_user_id !== null) search.set('actor_user_id', String(filters.actor_user_id))
    if (filters.created_from !== null) search.set('created_from', filters.created_from)
    if (filters.created_to !== null) search.set('created_to', filters.created_to)
    const qs = search.toString()
    const today = new Date().toISOString().slice(0, 10)

    void apiClient
      .getBlob(`/admin/audit-logs/export${qs === '' ? '' : `?${qs}`}`)
      .then((blob) => {
        const url = URL.createObjectURL(blob)
        const a = document.createElement('a')
        a.href = url
        a.download = `audit-logs-${today}.csv`
        document.body.appendChild(a)
        a.click()
        document.body.removeChild(a)
        URL.revokeObjectURL(url)
      })
      .catch(() => {
        setErrorMessage(t('admin.audit.exportError'))
      })
      .finally(() => {
        setIsDownloading(false)
      })
  }

  return { download, isDownloading, errorMessage }
}
