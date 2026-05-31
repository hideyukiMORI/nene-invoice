import { useState } from 'react'
import { apiClient } from '@/shared/api/client'
import { useTranslation } from '@/shared/i18n'
import type { QuoteId } from './ids'

export interface UseDownloadQuotePdf {
  download: () => void
  isDownloading: boolean
  errorMessage: string | null
}

/** Downloads the quote PDF via Bearer-authenticated fetch and triggers a save dialog. */
export function useDownloadQuotePdf(quoteId: QuoteId, quoteNumber: string): UseDownloadQuotePdf {
  const { t } = useTranslation()
  const [isDownloading, setIsDownloading] = useState(false)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)

  const download = (): void => {
    if (isDownloading) return
    setIsDownloading(true)
    setErrorMessage(null)

    void apiClient
      .getBlob(`/admin/quotes/${String(quoteId)}/pdf`)
      .then((blob) => {
        const url = URL.createObjectURL(blob)
        const a = document.createElement('a')
        a.href = url
        a.download = `${quoteNumber}.pdf`
        document.body.appendChild(a)
        a.click()
        document.body.removeChild(a)
        URL.revokeObjectURL(url)
      })
      .catch(() => {
        setErrorMessage(t('admin.quotes.detail.downloadPdfError'))
      })
      .finally(() => {
        setIsDownloading(false)
      })
  }

  return { download, isDownloading, errorMessage }
}
