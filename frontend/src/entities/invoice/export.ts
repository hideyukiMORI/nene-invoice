import { useState } from 'react'
import { apiClient } from '@/shared/api/client'
import { useTranslation } from '@/shared/i18n'

export interface UseExportCsv {
  download: () => void
  isDownloading: boolean
  errorMessage: string | null
}

function useExportCsvBase(path: string, filename: string, errorKey: string): UseExportCsv {
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
        setErrorMessage(t(errorKey as Parameters<typeof t>[0]))
      })
      .finally(() => {
        setIsDownloading(false)
      })
  }

  return { download, isDownloading, errorMessage }
}

/** Downloads all issued invoices as CSV. */
export function useExportInvoicesCsv(): UseExportCsv {
  const today = new Date().toISOString().slice(0, 10)
  return useExportCsvBase(
    '/admin/invoices/export',
    `invoices-${today}.csv`,
    'admin.invoices.export.error',
  )
}

/** Downloads all payments as CSV. */
export function useExportPaymentsCsv(): UseExportCsv {
  const today = new Date().toISOString().slice(0, 10)
  return useExportCsvBase(
    '/admin/payments/export',
    `payments-${today}.csv`,
    'admin.invoices.export.paymentsError',
  )
}
