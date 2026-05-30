import { useState } from 'react'
import { apiClient } from '@/shared/api/client'
import type { InvoiceId } from './ids'

export interface UseDownloadInvoicePdf {
  download: () => void
  isDownloading: boolean
  error: boolean
}

/** Downloads the invoice PDF via Bearer-authenticated fetch and triggers a save dialog. */
export function useDownloadInvoicePdf(
  invoiceId: InvoiceId,
  invoiceNumber: string | null,
): UseDownloadInvoicePdf {
  const [isDownloading, setIsDownloading] = useState(false)
  const [error, setError] = useState(false)

  const download = (): void => {
    if (isDownloading) return
    setIsDownloading(true)
    setError(false)

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
        setError(true)
      })
      .finally(() => {
        setIsDownloading(false)
      })
  }

  return { download, isDownloading, error }
}
