import { useState } from 'react'
import { apiClient } from '@/shared/api/client'
import { useTranslation } from '@/shared/i18n'
import type { InvoiceId } from './ids'

export interface UseDownloadInvoicePdf {
  /** True only when the invoice has a number (i.e. is issued). Drafts return false. */
  canDownload: boolean
  download: () => void
  isDownloading: boolean
  errorMessage: string | null
}

/** Downloads the invoice PDF via Bearer-authenticated fetch and triggers a save dialog. */
export function useDownloadInvoicePdf(
  invoiceId: InvoiceId,
  invoiceNumber: string | null,
): UseDownloadInvoicePdf {
  const { t } = useTranslation()
  const [isDownloading, setIsDownloading] = useState(false)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)

  const download = (): void => {
    if (isDownloading) return
    setIsDownloading(true)
    setErrorMessage(null)

    void apiClient
      .getBlob(`/admin/invoices/${String(invoiceId)}/pdf`)
      .then((blob) => {
        const url = URL.createObjectURL(blob)
        const a = document.createElement('a')
        a.href = url
        a.download =
          invoiceNumber !== null ? `${invoiceNumber}.pdf` : `invoice-${String(invoiceId)}.pdf`
        document.body.appendChild(a)
        a.click()
        document.body.removeChild(a)
        URL.revokeObjectURL(url)
      })
      .catch(() => {
        setErrorMessage(t('admin.invoices.detail.downloadPdfError'))
      })
      .finally(() => {
        setIsDownloading(false)
      })
  }

  return { canDownload: invoiceNumber !== null, download, isDownloading, errorMessage }
}
