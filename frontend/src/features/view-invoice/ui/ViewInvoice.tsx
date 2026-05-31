import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useDownloadInvoicePdf, useSendInvoiceEmail, type InvoiceId } from '@/entities/invoice'
import { useTranslation } from '@/shared/i18n'
import { formatTaxRate, formatYen } from '@/shared/lib/format-money'
import { Button, ErrorState, Spinner, Stack, Text } from '@/shared/ui'
import { useGenerateDownloadLink } from '../hooks/use-generate-download-link'
import { useViewInvoice } from '../hooks/use-view-invoice'

export interface ViewInvoiceProps {
  invoiceId: InvoiceId
}

/** Invoice detail: header summary, line items, and totals. */
export function ViewInvoice({ invoiceId }: ViewInvoiceProps) {
  const { t } = useTranslation()
  const state = useViewInvoice(invoiceId)
  // Hooks must be called unconditionally — before any early return.
  const invoiceNumber = state.kind === 'ready' ? state.invoice.invoice_number : null
  const isIssued = state.kind === 'ready' && state.invoice.status !== 'draft'
  const pdf = useDownloadInvoicePdf(invoiceId, invoiceNumber)
  const sendEmail = useSendInvoiceEmail()
  const [emailSent, setEmailSent] = useState(false)
  const link = useGenerateDownloadLink(invoiceId, isIssued)

  if (state.kind === 'loading') {
    return (
      <Stack direction="row" gap="sm">
        <Spinner label={t('admin.invoices.loading')} />
        <Text variant="muted">{t('admin.invoices.loading')}</Text>
      </Stack>
    )
  }

  if (state.kind === 'error') {
    return (
      <ErrorState
        message={t('admin.invoices.detail.error')}
        retryLabel={t('common.actions.retry')}
        onRetry={state.retry}
      />
    )
  }

  const invoice = state.invoice

  return (
    <Stack gap="lg">
      <Stack gap="sm">
        <Link to="/invoices" className="text-body text-accent">
          ← {t('admin.invoices.detail.backToList')}
        </Link>
        <div className="flex items-start justify-between">
          <Text as="h1" variant="heading-md">
            {invoice.invoice_number ?? t('admin.invoices.detail.notIssued')}
          </Text>
          {pdf.canDownload && (
            <Stack gap="sm">
              <Button onClick={pdf.download} disabled={pdf.isDownloading}>
                {pdf.isDownloading
                  ? t('admin.invoices.detail.downloadingPdf')
                  : t('admin.invoices.detail.downloadPdf')}
              </Button>
              {pdf.errorMessage !== null && (
                <Text variant="muted" role="alert">
                  {pdf.errorMessage}
                </Text>
              )}
            </Stack>
          )}
          {isIssued && (
            <Stack gap="sm">
              <Button
                onClick={() => {
                  setEmailSent(false)
                  sendEmail.mutate(invoiceId, {
                    onSuccess: () => {
                      setEmailSent(true)
                    },
                  })
                }}
                disabled={sendEmail.isPending}
              >
                {sendEmail.isPending
                  ? t('admin.invoices.detail.sendingEmail')
                  : t('admin.invoices.detail.sendEmail')}
              </Button>
              {emailSent && (
                <Text variant="muted" role="status">
                  {t('admin.invoices.detail.emailSent')}
                </Text>
              )}
              {sendEmail.isError && (
                <Text variant="muted" role="alert">
                  {t('admin.invoices.detail.emailError')}
                </Text>
              )}
            </Stack>
          )}
          {link.canGenerate && (
            <Stack gap="sm">
              <Button onClick={link.generate} disabled={link.isGenerating}>
                {link.isGenerating
                  ? t('admin.invoices.detail.generatingLink')
                  : t('admin.invoices.detail.generateLink')}
              </Button>
              {link.downloadUrl !== null && (
                <Stack gap="sm">
                  <Text variant="muted" className="break-all text-caption">
                    {`${window.location.origin}${link.downloadUrl}`}
                  </Text>
                  <Stack direction="row" gap="sm">
                    <Button onClick={link.copy}>
                      {link.copied
                        ? t('admin.invoices.detail.linkCopied')
                        : t('admin.invoices.detail.linkCopy')}
                    </Button>
                    {link.expiresAt !== null && (
                      <Text variant="muted">
                        {t('admin.invoices.detail.linkExpiry', { expiresAt: link.expiresAt })}
                      </Text>
                    )}
                  </Stack>
                </Stack>
              )}
              {link.errorMessage !== null && (
                <Text variant="muted" role="alert">
                  {link.errorMessage}
                </Text>
              )}
            </Stack>
          )}
        </div>
        <Stack direction="row" gap="md">
          <Text variant="muted">{t(`admin.invoices.status.${invoice.status}`)}</Text>
          {invoice.is_overdue && (
            <Text variant="muted" className="text-error font-medium">
              {t('admin.invoices.status.overdue')}
            </Text>
          )}
          {invoice.is_qualified_invoice && (
            <Text variant="muted">{t('admin.invoices.detail.qualified')}</Text>
          )}
          {invoice.issued_at !== null && (
            <Text variant="muted">
              {t('admin.invoices.detail.issuedAt')}: {invoice.issued_at}
            </Text>
          )}
          {invoice.due_at !== null && (
            <Text variant="muted">
              {t('admin.invoices.detail.dueAt')}: {invoice.due_at}
            </Text>
          )}
        </Stack>
      </Stack>

      <table className="w-full border-collapse text-body">
        <thead>
          <tr className="border-b border-border text-left">
            <th className="py-stack-sm pr-inline-md font-medium">
              {t('admin.invoices.line.description')}
            </th>
            <th className="py-stack-sm pr-inline-md text-right font-medium">
              {t('admin.invoices.line.quantity')}
            </th>
            <th className="py-stack-sm pr-inline-md text-right font-medium">
              {t('admin.invoices.line.unitPrice')}
            </th>
            <th className="py-stack-sm pr-inline-md text-right font-medium">
              {t('admin.invoices.line.taxRate')}
            </th>
            <th className="py-stack-sm text-right font-medium">
              {t('admin.invoices.line.lineSubtotal')}
            </th>
          </tr>
        </thead>
        <tbody>
          {invoice.line_items.map((line, index) => (
            <tr key={index} className="border-b border-border">
              <td className="py-stack-sm pr-inline-md">{line.description}</td>
              <td className="py-stack-sm pr-inline-md text-right">{line.quantity}</td>
              <td className="py-stack-sm pr-inline-md text-right">
                {formatYen(line.unit_price_cents)}
              </td>
              <td className="py-stack-sm pr-inline-md text-right">
                {formatTaxRate(line.tax_rate_bps)}
              </td>
              <td className="py-stack-sm text-right">{formatYen(line.line_subtotal_cents)}</td>
            </tr>
          ))}
        </tbody>
      </table>

      <Stack gap="sm" className="ml-auto w-64">
        <TotalRow
          label={t('admin.invoices.detail.subtotal')}
          value={formatYen(invoice.subtotal_cents)}
        />
        <TotalRow label={t('admin.invoices.detail.tax')} value={formatYen(invoice.tax_cents)} />
        <TotalRow label={t('admin.invoices.detail.total')} value={formatYen(invoice.total_cents)} />
        {invoice.outstanding_cents !== null && (
          <TotalRow
            label={t('admin.invoices.detail.outstanding')}
            value={formatYen(invoice.outstanding_cents)}
          />
        )}
      </Stack>
    </Stack>
  )
}

function TotalRow({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex justify-between">
      <Text variant="muted">{label}</Text>
      <Text>{value}</Text>
    </div>
  )
}
