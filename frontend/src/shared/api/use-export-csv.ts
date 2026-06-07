import { useState } from 'react'
import { apiClient } from '@/shared/api/client'
import { useTranslation } from '@/shared/i18n'
import type { MessageKey } from '@/shared/i18n'

export interface UseExportCsv {
  download: () => void
  isDownloading: boolean
  errorMessage: string | null
}

/**
 * Generic "download a CSV blob from `path`" hook. Tracks an in-flight flag and
 * surfaces a translated error. Shared by the list-export hooks (invoices,
 * payments, quotes) so the download mechanics live in one place.
 */
export function useExportCsvBase(
  path: string,
  filename: string,
  errorKey: MessageKey,
): UseExportCsv {
  const { t } = useTranslation()
  const [isDownloading, setIsDownloading] = useState(false)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)

  const download = (): void => {
    if (isDownloading) return
    setIsDownloading(true)
    setErrorMessage(null)

    void apiClient
      .getBlob(path)
      .then((blob) => {
        const url = URL.createObjectURL(blob)
        const a = document.createElement('a')
        a.href = url
        a.download = filename
        document.body.appendChild(a)
        a.click()
        document.body.removeChild(a)
        URL.revokeObjectURL(url)
      })
      .catch(() => {
        setErrorMessage(t(errorKey))
      })
      .finally(() => {
        setIsDownloading(false)
      })
  }

  return { download, isDownloading, errorMessage }
}
