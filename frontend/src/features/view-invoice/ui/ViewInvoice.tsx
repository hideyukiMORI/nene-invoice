import { Link } from 'react-router-dom'
import { useDownloadInvoicePdf, useSendInvoiceEmail, type InvoiceId } from '@/entities/invoice'
import { useTranslation } from '@/shared/i18n'
import { formatYen } from '@/shared/lib/format-money'
import { Button, ErrorState, LineItemsTable, LoadingState, MutationError, Stack, Text, TotalRow } from '@/shared/ui'
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
  const link = useGenerateDownloadLink(invoiceId, isIssued)

  if (state.kind === 'loading') {
    return <LoadingState message={t('admin.invoices.loading')} />
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
              <MutationError message={pdf.errorMessage} />
            </Stack>
          )}
          {isIssued && (
            <Stack gap="sm">
              <Button
                onClick={() => {
                  sendEmail.mutate(invoiceId)
                }}
                disabled={sendEmail.isPending}
              >
                {sendEmail.isPending
                  ? t('admin.invoices.detail.sendingEmail')
                  : t('admin.invoices.detail.sendEmail')}
              </Button>
              {sendEmail.isSuccess && (
                <Text variant="muted" role="status">
                  {t('admin.invoices.detail.emailSent')}
                </Text>
              )}
              <MutationError message={sendEmail.isError ? t('admin.invoices.detail.emailError') : null} />
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
              <MutationError message={link.errorMessage} />
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

      <LineItemsTable items={invoice.line_items} />

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
