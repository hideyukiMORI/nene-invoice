import { useState } from 'react'
import { useGenerateDownloadToken, type InvoiceId } from '@/entities/invoice'
import { useTranslation } from '@/shared/i18n'

export interface UseGenerateDownloadLink {
  canGenerate: boolean
  generate: () => void
  isGenerating: boolean
  downloadUrl: string | null
  expiresAt: string | null
  copied: boolean
  copy: () => void
  errorMessage: string | null
}

export function useGenerateDownloadLink(
  invoiceId: InvoiceId,
  isIssued: boolean,
): UseGenerateDownloadLink {
  const { t } = useTranslation()
  const mutation = useGenerateDownloadToken()
  const [copied, setCopied] = useState(false)

  const downloadUrl = mutation.data?.url ?? null
  const expiresAt = mutation.data?.expires_at ?? null

  const copy = (): void => {
    if (downloadUrl === null) return
    const absolute = `${window.location.origin}${downloadUrl}`
    void navigator.clipboard.writeText(absolute).then(() => {
      setCopied(true)
      setTimeout(() => {
        setCopied(false)
      }, 2000)
    })
  }

  return {
    canGenerate: isIssued,
    generate: () => {
      mutation.mutate(invoiceId)
    },
    isGenerating: mutation.isPending,
    downloadUrl,
    expiresAt: expiresAt !== null ? expiresAt.slice(0, 10) : null,
    copied,
    copy,
    errorMessage: mutation.isError ? t('admin.invoices.detail.generateLinkError') : null,
  }
}
